<?php
/* Copyright (C) 2013-2016 Jean-François FERRY  <hello@librethic.io>
 * Copyright (C) 2016      Christophe Battarel  <christophe@altairis.fr>
 * Copyright (C) 2018      Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2021      Frédéric France		<frederic.france@netlogic.fr>
 * Copyright (C) 2021      Alexandre Spangaro   <aspangaro@open-dsi.fr>
 * Copyright (C) 2022      Charlene Benke       <charlene@patas-monkey.com>
 * Copyright (C) 2023      Benjamin Falière		<benjamin.faliere@altairis.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *    \file     htdocs/ticket/card.php
 *    \ingroup 	ticket
 */

// Load Dolibarr environment
require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/ticket/class/actions_ticket.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formticket.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/ticket.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
if (isModEnabled('project')) {
	include_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
	include_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
	include_once DOL_DOCUMENT_ROOT.'/core/lib/project.lib.php';
}
if (isModEnabled('contrat')) {
	include_once DOL_DOCUMENT_ROOT.'/core/lib/contract.lib.php';
	include_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
	include_once DOL_DOCUMENT_ROOT.'/core/class/html.formcontract.class.php';
}

// Load translation files required by the page
$langs->loadLangs(array("companies", "other", "ticket"));

// Get parameters
$id        = GETPOST('id', 'int');
$socid     = GETPOST('socid', 'int');
$track_id  = GETPOST('track_id', 'alpha', 3);
$ref       = GETPOST('ref', 'alpha');
$projectid = GETPOST('projectid', 'int');
$cancel    = GETPOST('cancel', 'alpha');
$action    = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');
$contactid = GETPOST('contactid', 'int');

$notifyTiers = GETPOST("notify_tiers_at_create", 'alpha');

$sortfield = GETPOST('sortfield', 'aZ09comma') ? GETPOST('sortfield', 'aZ09comma') : "a.datep";
$sortorder = GETPOST('sortorder', 'aZ09comma') ? GETPOST('sortorder', 'aZ09comma') : "desc";

if (GETPOST('actioncode', 'array')) {
	$actioncode = GETPOST('actioncode', 'array', 3);
	if (!count($actioncode)) {
		$actioncode = '0';
	}
} else {
	$actioncode = GETPOST("actioncode", "alpha", 3) ?GETPOST("actioncode", "alpha", 3) : (GETPOST("actioncode") == '0' ? '0' : getDolGlobalString('AGENDA_DEFAULT_FILTER_TYPE_FOR_OBJECT'));
}
$search_rowid = GETPOST('search_rowid');
$search_agenda_label = GETPOST('search_agenda_label');

// Initialize technical object to manage hooks of ticket. Note that conf->hooks_modules contains array array
$hookmanager->initHooks(array('ticketcard', 'globalcard'));

$object = new Ticket($db);
$extrafields = new ExtraFields($db);

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// Initialize array of search criterias
$search_all = GETPOST("search_all", 'alpha');
$search = array();
foreach ($object->fields as $key => $val) {
	if (GETPOST('search_'.$key, 'alpha')) {
		$search[$key] = GETPOST('search_'.$key, 'alpha');
	}
}

if (empty($action) && empty($id) && empty($ref)) {
	$action = 'view';
}

//Select mail models is same action as add_message
if (GETPOST('modelselected', 'alpha')) {
	$action = 'presend';
}

// Load object
//include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';  // Must be include, not include_once  // Must be include, not include_once. Include fetch and fetch_thirdparty but not fetch_optionals
if ($id || $track_id || $ref) {
	$res = $object->fetch($id, $ref, $track_id);
	if ($res >= 0) {
		$id = $object->id;
		$track_id = $object->track_id;
	}
}

$now = dol_now();

$actionobject = new ActionsTicket($db);

// Store current page url
$url_page_current = DOL_URL_ROOT.'/ticket/card.php';

// Security check - Protection if external user
if ($user->socid > 0) $socid = $user->socid;
$result = restrictedArea($user, 'ticket', $object->id);

$triggermodname = 'TICKET_MODIFY';
$permissiontoread = $user->hasRight('ticket', 'read');
$permissiontoadd = $user->hasRight('ticket', 'write');
$permissiontodelete = $user->hasRight('ticket', 'delete');

$upload_dir = $conf->ticket->dir_output;



/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

$error = 0;
if (empty($reshook)) {
	// Purge search criteria
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // All test are required to be compatible with all browsers{
		$actioncode = '';
		$search_agenda_label = '';
	}

	$backurlforlist = DOL_URL_ROOT.'/ticket/list.php';

	if (empty($backtopage) || ($cancel && empty($id))) {
		if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
			if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) {
				$backtopage = $backurlforlist;
			} else {
				$backtopage = DOL_URL_ROOT.'/ticket/card.php?id='.((!empty($id) && $id > 0) ? $id : '__ID__');
			}
		}
	}

	if ($cancel) {
		if (!empty($backtopageforcancel)) {
			header("Location: ".$backtopageforcancel);
			exit;
		} elseif (!empty($backtopage)) {
			header("Location: ".$backtopage);
			exit;
		}
		$action = 'view';
	}

	// Action to add an action (not a message)
	if (GETPOST('save', 'alpha') && $permissiontoadd) {
		$error = 0;

		if (!GETPOST("type_code", 'alpha')) {
			$error++;
			setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentities("TicketTypeRequest")), null, 'errors');
			$action = 'create';
		} elseif (!GETPOST("category_code", 'alpha')) {
			$error++;
			setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentities("TicketCategory")), null, 'errors');
			$action = 'create';
		} elseif (!GETPOST("severity_code", 'alpha')) {
			$error++;
			setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentities("TicketSeverity")), null, 'errors');
			$action = 'create';
		} elseif (!GETPOST("subject", 'alphanohtml')) {
			$error++;
			setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentities("Subject")), null, 'errors');
			$action = 'create';
		} elseif (!GETPOST("message", 'restricthtml')) {
			$error++;
			setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentities("Message")), null, 'errors');
			$action = 'create';
		}
		$ret = $extrafields->setOptionalsFromPost(null, $object);
		if ($ret < 0) {
			$error++;
		}

		if (!$error) {
			$db->begin();

			$getRef = GETPOST("ref", 'alphanohtml');
			$refcheck_object = new Ticket($db);
			if ($refcheck_object->fetch('', $getRef) > 0) {
				$object->ref = $object->getDefaultRef();
				$object->track_id = null;
				setEventMessage($langs->trans('TicketRefAlreadyUsed', $getRef, $object->ref));
			} else {
				$object->ref = $getRef;
			}

			$object->fk_soc = GETPOST("socid", 'int') > 0 ? GETPOST("socid", 'int') : 0;
			$object->subject = GETPOST("subject", 'alphanohtml');
			$object->message = GETPOST("message", 'restricthtml');

			$object->type_code = GETPOST("type_code", 'alpha');
			$object->type_label = $langs->trans($langs->getLabelFromKey($db, $object->type_code, 'c_ticket_type', 'code', 'label'));
			$object->category_code = GETPOST("category_code", 'alpha');
			$object->category_label = $langs->trans($langs->getLabelFromKey($db, $object->category_code, 'c_ticket_category', 'code', 'label'));
			$object->severity_code = GETPOST("severity_code", 'alpha');
			$object->severity_label = $langs->trans($langs->getLabelFromKey($db, $object->severity_code, 'c_ticket_severity', 'code', 'label'));
			$object->email_from = $user->email;
			$notifyTiers = GETPOST("notify_tiers_at_create", 'alpha');
			$object->notify_tiers_at_create = empty($notifyTiers) ? 0 : 1;
			$fk_user_assign = GETPOST("fk_user_assign", 'int');
			if ($fk_user_assign > 0) {
				$object->fk_user_assign = $fk_user_assign;
				$object->status = $object::STATUS_ASSIGNED;
			}

			$object->fk_project = $projectid;

			$id = $object->create($user);
			if ($id <= 0) {
				$error++;
				setEventMessages($object->error, $object->errors, 'errors');
				$action = 'create';
			}

			if (!$error) {
				// Add contact
				$contactid = GETPOST('contactid', 'int');
				$type_contact = GETPOST("type", 'alpha');

				// Category association
				$categories = GETPOST('categories', 'array');
				$object->setCategories($categories);

				if ($contactid > 0 && $type_contact) {
					$typeid = (GETPOST('typecontact') ? GETPOST('typecontact') : GETPOST('type'));
					$result = $object->add_contact($contactid, $typeid, 'external');
				}

				// Link ticket to project
				if (GETPOST('origin', 'alpha') == 'projet') {
					$projectid = GETPOST('originid', 'int');
				} else {
					$projectid = GETPOST('projectid', 'int');
				}

				if ($projectid > 0) {
					$object->setProject($projectid);
				}

				// Auto mark as read if created from backend
				if (getDolGlobalString('TICKET_AUTO_READ_WHEN_CREATED_FROM_BACKEND') && $user->rights->ticket->write) {
					if ( ! $object->markAsRead($user) > 0) {
						setEventMessages($object->error, $object->errors, 'errors');
					}
				}

				// Auto assign user
				if (getDolGlobalString('TICKET_AUTO_ASSIGN_USER_CREATE')) {
					$result = $object->assignUser($user, $user->id, 1);
					$object->add_contact($user->id, "SUPPORTTEC", 'internal');
				}
			}

			if (!$error) {
				// File transfer
				$object->copyFilesForTicket('');		// trackid is forced to '' because files were uploaded when no id for ticket exists yet and trackid was ''
			}

			if (!$error) {
				$db->commit();

				if (!empty($backtopage)) {
					if (empty($id)) {
						$url = $backtopage;
					} else {
						$url = 'card.php?track_id='.urlencode($object->track_id);
					}
				} else {
					$url = 'card.php?track_id='.urlencode($object->track_id);
				}

				header("Location: ".$url);
				exit;
			} else {
				$db->rollback();
				setEventMessages($object->error, $object->errors, 'errors');
			}
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
			$action = 'create';
		}
	}

	if ($action == 'update' && $permissiontoadd && $object->status < Ticket::STATUS_CLOSED) {
		$error = 0;

		$ret = $object->fetch(GETPOST('id', 'int'), GETPOST('ref', 'alpha'), GETPOST('track_id', 'alpha'));
		if ($ret < 0) {
			$error++;
			array_push($object->errors, $langs->trans('ErrorTicketIsNotValid'));
		}

		// check fields
		if (!$error) {
			if (!GETPOST('subject', 'alpha')) {
				$error++;
				array_push($object->errors, $langs->trans('ErrorFieldRequired', $langs->transnoentities('Subject')));
			}
			$ret = $extrafields->setOptionalsFromPost(null, $object);
			if ($ret < 0) {
				$error++;
			}
		}

		if (!$error) {
			$db->begin();

			$object->subject = GETPOST('subject', 'alpha');
			$object->type_code = GETPOST('type_code', 'alpha');
			$object->category_code = GETPOST('category_code', 'alpha');
			$object->severity_code = GETPOST('severity_code', 'alpha');

			$ret = $object->update($user);
			if ($ret > 0) {
				// Category association
				$categories = GETPOST('categories', 'array');
				$object->setCategories($categories);
			} else {
				$error++;
			}

			if ($error) {
				$db->rollback();
			} else {
				$db->commit();
			}
		}

		if ($error) {
			setEventMessages($object->error, $object->errors, 'errors');
			$action = 'edit';
		} else {
			if (!empty($backtopage)) {
				if (empty($id)) {
					$url = $backtopage;
				} else {
					$url = 'card.php?track_id='.urlencode($object->track_id);
				}
			} else {
				$url = 'card.php?track_id='.urlencode($object->track_id);
			}

			header('Location: '.$url);
			exit();
		}
	}

	// Mark as Read
	if ($action == "set_read" && $permissiontoadd) {
		$object->fetch('', '', GETPOST("track_id", 'alpha'));

		if ($object->markAsRead($user) > 0) {
			setEventMessages($langs->trans('TicketMarkedAsRead'), null, 'mesgs');

			header("Location: card.php?track_id=".$object->track_id);
			exit;
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		$action = 'view';
	}

	// Assign to someone
	if ($action == "assign_user" && GETPOST('btn_assign_user', 'alpha') && $permissiontoadd) {
		$object->fetch('', '', GETPOST("track_id", 'alpha'));
		$useroriginassign = $object->fk_user_assign;
		$usertoassign = GETPOST('fk_user_assign', 'int');

		/*if (! ($usertoassign > 0)) {
		 $error++;
		 array_push($object->errors, $langs->trans("ErrorFieldRequired", $langs->transnoentities("AssignedTo")));
		 $action = 'view';
		 }*/

		if (!$error) {
			$ret = $object->assignUser($user, $usertoassign);
			if ($ret < 0) {
				$error++;
			}
		}

		if (!$error) {    // Update list of contacts
			// Si déjà un user assigné on le supprime des contacts
			if ($useroriginassign > 0) {
				$internal_contacts = $object->listeContact(-1, 'internal', 0, 'SUPPORTTEC');
				foreach ($internal_contacts as $key => $contact) {
					if ($contact['id'] !== $usertoassign) {
						$result = $object->delete_contact($contact['rowid']);
						if ($result<0) {
							$error++;
							setEventMessages($object->error, $object->errors, 'errors');
						}
					}
				}
			}

			if ($usertoassign > 0 && $usertoassign!==$useroriginassign) {
				$result = $object->add_contact($usertoassign, "SUPPORTTEC", 'internal', $notrigger = 0);
				if ($result<0) {
					$error++;
					setEventMessages($object->error, $object->errors, 'errors');
				}
			}
		}

		if (!$error) {
			// Log action in ticket logs table
			$object->fetch_user($usertoassign);
			//$log_action = $langs->trans('TicketLogAssignedTo', $object->user->getFullName($langs));


			setEventMessages($langs->trans('TicketAssigned'), null, 'mesgs');
			header("Location: card.php?track_id=".$object->track_id);
			exit;
		} else {
			array_push($object->errors, $object->error);
		}
		$action = 'view';
	}

	// Action to add a message (private or not, with email or not).
	// This may also send an email (concatenated with email_intro and email footer if checkbox was selected)
	if ($action == 'add_message' && GETPOSTISSET('btn_add_message') && $permissiontoread) {
		$ret = $object->newMessage($user, $action, GETPOSTINT('private_message'), 0);

		if ($ret > 0) {
			if (!empty($backtopage)) {
				$url = $backtopage;
			} else {
				$url = 'card.php?track_id='.urlencode($object->track_id);
			}

			header("Location: ".$url);
			exit;
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
			$action = 'presend';
		}
	}

	if (($action == "confirm_close" || $action == "confirm_abandon") && GETPOST('confirm', 'alpha') == 'yes' && $permissiontoadd) {
		$object->fetch(GETPOST('id', 'int'), '', GETPOST('track_id', 'alpha'));

		if ($object->close($user, ($action == "confirm_abandon" ? 1 : 0))) {
			setEventMessages($langs->trans('TicketMarkedAsClosed'), null, 'mesgs');

			$url = 'card.php?track_id='.GETPOST('track_id', 'alpha');
			header("Location: ".$url);
		} else {
			$action = '';
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}

	if ($action == "confirm_public_close" && GETPOST('confirm', 'alpha') == 'yes' && $permissiontoadd) {
		$object->fetch(GETPOST('id', 'int'), '', GETPOST('track_id', 'alpha'));
		if ($_SESSION['email_customer'] == $object->origin_email || $_SESSION['email_customer'] == $object->thirdparty->email) {
			$object->close($user);

			// Log action in ticket logs table
			//$log_action = $langs->trans('TicketLogClosedBy', $_SESSION['email_customer']);

			setEventMessages('<div class="confirm">'.$langs->trans('TicketMarkedAsClosed').'</div>', null, 'mesgs');

			$url = 'card.php?track_id='.GETPOST('track_id', 'alpha');
			header("Location: ".$url);
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
			$action = '';
		}
	}

	if ($action == 'confirm_delete_ticket' && GETPOST('confirm', 'alpha') == "yes" && $permissiontodelete) {
		if ($object->fetch(GETPOST('id', 'int'), '', GETPOST('track_id', 'alpha')) >= 0) {
			if ($object->delete($user) > 0) {
				setEventMessages('<div class="confirm">'.$langs->trans('TicketDeletedSuccess').'</div>', null, 'mesgs');
				Header("Location: ".DOL_URL_ROOT."/ticket/list.php");
				exit;
			} else {
				$langs->load("errors");
				$mesg = '<div class="error">'.$langs->trans($object->error).'</div>';
				$action = '';
			}
		}
	}

	// Set parent company
	if ($action == 'set_thirdparty' && $user->rights->ticket->write) {
		if ($object->fetch(GETPOST('id', 'int'), '', GETPOST('track_id', 'alpha')) >= 0) {
			$result = $object->setCustomer(GETPOST('editcustomer', 'int'));
			$url = $_SERVER["PHP_SELF"].'?track_id='.GETPOST('track_id', 'alpha');
			header("Location: ".$url);
			exit();
		}
	}

	if ($action == 'set_progression' && $user->rights->ticket->write) {
		if ($object->fetch(GETPOST('id', 'int'), '', GETPOST('track_id', 'alpha')) >= 0) {
			$result = $object->setProgression(GETPOST('progress', 'alpha'));

			$url = 'card.php?track_id='.$object->track_id;
			header("Location: ".$url);
			exit();
		}
	}

	if ($action == 'set_categories' && $user->rights->ticket->write) {
		if ($object->fetch(GETPOST('id', 'int'), '', GETPOST('track_id', 'alpha')) >= 0) {
			$result = $object->setCategories(GETPOST('categories', 'array'));

			$url = 'card.php?track_id='.$object->track_id;
			header("Location: ".$url);
			exit();
		}
	}

	if ($action == 'setsubject' && $user->rights->ticket->write) {
		if ($object->fetch(GETPOST('id', 'int'))) {
			if ($action == 'setsubject') {
				$object->subject = GETPOST('subject', 'alphanohtml');
			}

			if ($action == 'setsubject' && empty($object->subject)) {
				$error++;
				setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentities("Subject")), null, 'errors');
			}

			if (!$error) {
				if ($object->update($user) >= 0) {
					header("Location: ".$_SERVER['PHP_SELF']."?track_id=".$object->track_id);
					exit;
				} else {
					$error++;
					setEventMessages($object->error, $object->errors, 'errors');
				}
			}
		}
	}

	if ($action == 'confirm_reopen' && $user->rights->ticket->manage && !GETPOST('cancel')) {
		if ($object->fetch(GETPOST('id', 'int'), '', GETPOST('track_id', 'alpha')) >= 0) {
			// prevent browser refresh from reopening ticket several times
			if ($object->status == Ticket::STATUS_CLOSED || $object->status == Ticket::STATUS_CANCELED) {
				$res = $object->setStatut(Ticket::STATUS_ASSIGNED);
				if ($res) {
					// Log action in ticket logs table
					//$log_action = $langs->trans('TicketLogReopen');

					$url = 'card.php?track_id='.$object->track_id;
					header("Location: ".$url);
					exit();
				} else {
					$error++;
					setEventMessages($object->error, $object->errors, 'errors');
				}
			}
		}
	} elseif ($action == 'classin' && $permissiontoadd) {
		// Categorisation dans projet
		if ($object->fetch(GETPOST('id', 'int'), '', GETPOST('track_id', 'alpha')) >= 0) {
			$object->setProject($projectid);
			$url = 'card.php?track_id='.$object->track_id;
			header("Location: ".$url);
			exit();
		}
	} elseif ($action == 'setcontract' && $permissiontoadd) {
		// Categorisation dans contrat
		if ($object->fetch(GETPOST('id', 'int'), '', GETPOST('track_id', 'alpha')) >= 0) {
			$object->setContract(GETPOST('contractid', 'int'));
			$url = 'card.php?track_id='.$object->track_id;
			header("Location: ".$url);
			exit();
		}
	} elseif ($action == "set_message" && $user->rights->ticket->manage) {
		if (!GETPOST('cancel')) {
			$object->fetch('', '', GETPOST('track_id', 'alpha'));
			$oldvalue_message = $object->message;
			$fieldtomodify = GETPOST('message_initial', 'restricthtml');

			$object->message = $fieldtomodify;
			$ret = $object->update($user);
			if ($ret > 0) {
				//$log_action = $langs->trans('TicketInitialMessageModified')." \n";
				// include the Diff class
				include_once DOL_DOCUMENT_ROOT.'/core/class/utils_diff.class.php';
				// output the result of comparing two files as plain text
				//$log_action .= Diff::toString(Diff::compare(strip_tags($oldvalue_message), strip_tags($object->message)));

				setEventMessages($langs->trans('TicketMessageSuccesfullyUpdated'), null, 'mesgs');
			} else {
				$error++;
				setEventMessages($object->error, $object->errors, 'errors');
			}
		}

		$action = 'view';
	} elseif ($action == 'confirm_set_status' && $permissiontoadd && !GETPOST('cancel')) {
		// Reopen ticket
		if ($object->fetch(GETPOST('id', 'int'), GETPOST('track_id', 'alpha')) >= 0) {
			$new_status = GETPOST('new_status', 'int');
			$old_status = $object->status;
			$res = $object->setStatut($new_status);
			if ($res) {
				// Log action in ticket logs table
				$log_action = $langs->trans('TicketLogStatusChanged', $langs->transnoentities($object->statuts_short[$old_status]), $langs->transnoentities($object->statuts_short[$new_status]));

				$url = 'card.php?track_id='.$object->track_id;
				header("Location: ".$url);
				exit();
			} else {
				$error++;
				setEventMessages($object->error, $object->errors, 'errors');
			}
		}
	}

	// Action to update one extrafield
	if ($action == "update_extras" && $permissiontoadd) {
		$object->fetch(GETPOST('id', 'int'), '', GETPOST('track_id', 'alpha'));

		$ret = $extrafields->setOptionalsFromPost(null, $object, GETPOST('attribute', 'restricthtml'));
		if ($ret < 0) {
			$error++;
		}

		if (!$error) {
			$result = $object->insertExtraFields(empty($triggermodname) ? '' : $triggermodname, $user);
			if ($result < 0) {
				$error++;
			}
		}

		if ($error) {
			setEventMessages($object->error, $object->errors, 'errors');
			$action = 'edit_extras';
		} else {
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
			$action = 'view';
		}
	}

	if ($action == "change_property" && GETPOST('btn_update_ticket_prop', 'alpha') && $permissiontoadd) {
		$object->fetch(GETPOST('id', 'int'), '', GETPOST('track_id', 'alpha'));

		$object->type_code = GETPOST('update_value_type', 'aZ09');
		$object->severity_code = GETPOST('update_value_severity', 'aZ09');
		$object->category_code = GETPOST('update_value_category', 'aZ09');

		$ret = $object->update($user);
		if ($ret > 0) {
			//$log_action = $langs->trans('TicketLogPropertyChanged', $oldvalue_label, $newvalue_label);

			setEventMessages($langs->trans('TicketUpdated'), null, 'mesgs');
		} else {
			$error++;
			setEventMessages($object->error, $object->errors, 'errors');
		}
		$action = 'view';
	}


	$permissiondellink = $user->rights->ticket->write;
	include DOL_DOCUMENT_ROOT.'/core/actions_dellink.inc.php'; // Must be include, not include_once

	// Actions to build doc
	include DOL_DOCUMENT_ROOT.'/core/actions_builddoc.inc.php';
	//var_dump($action);exit;

	// Actions to send emails
	$triggersendname = 'TICKET_SENTBYMAIL';
	$paramname = 'id';
	$autocopy = 'MAIN_MAIL_AUTOCOPY_TICKET_TO'; // used to know the automatic BCC to add
	$trackid = 'tic'.$object->id;
	include DOL_DOCUMENT_ROOT.'/core/actions_sendmails.inc.php';

	// Set $action to correct value for the case we used presend action to add a message
	if (GETPOSTISSET('actionbis') && $action == 'presend') {
		$action = 'presend_addmessage';
	}
}


/*
 * View
 */

$userstat = new User($db);
$form = new Form($db);
$formticket = new FormTicket($db);
if (isModEnabled('project')) {
	$formproject = new FormProjets($db);
}

$help_url = 'EN:Module_Ticket|FR:DocumentationModuleTicket';

$title = $actionobject->getTitle($action);

llxHeader('', $title, $help_url);

if ($action == 'create' || $action == 'presend') {
	$formticket = new FormTicket($db);

	print load_fiche_titre($langs->trans('NewTicket'), '', 'ticket');

	$formticket->trackid = '';		// TODO Use a unique key 'tic' to avoid conflict in upload file feature
	$formticket->withfromsocid = $socid ? $socid : $user->socid;
	$formticket->withfromcontactid = $contactid ? $contactid : '';
	$formticket->withtitletopic = 1;
	$formticket->withnotifytiersatcreate = ($notifyTiers ? 1 : (getDolGlobalString('TICKET_CHECK_NOTIFY_THIRDPARTY_AT_CREATION') ? 1 : 0));
	$formticket->withusercreate = 0;
	$formticket->withref = 1;
	$formticket->fk_user_create = $user->id;
	$formticket->withfile = 2;
	$formticket->withextrafields = 1;
	$formticket->param = array('origin' => GETPOST('origin'), 'originid' => GETPOST('originid'));

	$formticket->withcancel = 1;

	$formticket->showForm(1, 'create', 0, null, $action);
	/*} elseif ($action == 'edit' && $user->rights->ticket->write && $object->status < Ticket::STATUS_CLOSED) {
	$formticket = new FormTicket($db);

	$head = ticket_prepare_head($object);

	print '<form method="POST" name="form_ticket" id="form_edit_ticket" action="'.$_SERVER['PHP_SELF'].'?track_id='.$object->track_id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="tack_id" value="'.$object->track_id.'">';

	print dol_get_fiche_head($head, 'card', $langs->trans('Ticket'), 0, 'ticket');

	print '<div class="fichecenter2">';
	print '<table class="border" width="100%">';

	// Type
	print '<tr><td class="titlefield"><span class="fieldrequired"><label for="selecttype_code">'.$langs->trans("TicketTypeRequest").'</span></label></td><td>';
	$formticket->selectTypesTickets((GETPOSTISSET('type_code') ? GETPOST('type_code') : $object->type_code), 'type_code', '', '2');
	print '</td></tr>';

	// Severity
	print '<tr><td><span class="fieldrequired"><label for="selectseverity_code">'.$langs->trans("TicketSeverity").'</span></label></td><td>';
	$formticket->selectSeveritiesTickets((GETPOSTISSET('severity_code') ? GETPOST('severity_code') : $object->severity_code), 'severity_code', '', '2');
	print '</td></tr>';

	// Group
	print '<tr><td><span class="fieldrequired"><label for="selectcategory_code">'.$langs->trans("TicketCategory").'</span></label></td><td>';
	$formticket->selectGroupTickets((GETPOSTISSET('category_code') ? GETPOST('category_code') : $object->category_code), 'category_code', '', '2');
	print '</td></tr>';

	// Subject
	print '<tr><td><label for="subject"><span class="fieldrequired">'.$langs->trans("Subject").'</span></label></td><td>';
	print '<input class="text minwidth200" id="subject" name="subject" value="'.dol_escape_htmltag(GETPOSTISSET('subject') ? GETPOST('subject', 'alpha') : $object->subject).'" />';
	print '</td></tr>';

	// Other attributes
	$parameters = array('colspan' => ' colspan="3"', 'colspanvalue' => '3');
	$reshook = $hookmanager->executeHooks('formObjectOptions', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
	print $hookmanager->resPrint;
	if (empty($reshook)) {
		print $object->showOptionals($extrafields, 'edit');
	}

	print '</table>';
	print '</div>';

	print dol_get_fiche_end();

	print $form->buttonsSaveCancel();

	print '</form>'; */
} elseif (empty($action) || $action == 'view' || $action == 'addlink' || $action == 'dellink' || $action == 'presend' || $action == 'presend_addmessage' || $action == 'close' || $action == 'abandon' || $action == 'delete' || $action == 'editcustomer' || $action == 'progression' || $action == 'categories' || $action == 'reopen'
	|| $action == 'editsubject' || $action == 'edit_extras' || $action == 'update_extras' || $action == 'edit_extrafields' || $action == 'set_extrafields' || $action == 'classify' || $action == 'sel_contract' || $action == 'edit_message_init' || $action == 'set_status' || $action == 'dellink') {
	if ($res > 0) {
		// or for unauthorized internals users
		if (!$user->socid && (getDolGlobalString('TICKET_LIMIT_VIEW_ASSIGNED_ONLY') && $object->fk_user_assign != $user->id) && !$user->rights->ticket->manage) {
			accessforbidden('', 0, 1);
		}

		// Confirmation close
		if ($action == 'close') {
			$thirdparty_contacts = $object->getInfosTicketExternalContact(1);
			$contacts_select = array(
				'-2' => $langs->trans('TicketNotifyAllTiersAtClose'),
				'-3' => $langs->trans('TicketNotNotifyTiersAtClose')
			);
			foreach ($thirdparty_contacts as $thirdparty_contact) {
				$contacts_select[$thirdparty_contact['id']] = $thirdparty_contact['civility'] . ' ' . $thirdparty_contact['lastname'] . ' ' . $thirdparty_contact['firstname'];
			}

			// Default select all or no contact
			$default = (getDolGlobalString('TICKET_NOTIFY_AT_CLOSING') ? -2 : -3);
			$formquestion = array(
				array(
					'name' => 'contactid',
					'type' => 'select',
					'label' => $langs->trans('NotifyThirdpartyOnTicketClosing'),
					'values' => $contacts_select,
					'default' => $default
				),
			);

			print $form->formconfirm($url_page_current."?track_id=".$object->track_id, $langs->trans("CloseATicket"), $langs->trans("ConfirmCloseAticket"), "confirm_close", $formquestion, '', 1);
		}
		// Confirmation abandon
		if ($action == 'abandon') {
			print $form->formconfirm($url_page_current."?track_id=".$object->track_id, $langs->trans("AbandonTicket"), $langs->trans("ConfirmAbandonTicket"), "confirm_abandon", '', '', 1);
		}
		// Confirmation delete
		if ($action == 'delete') {
			print $form->formconfirm($url_page_current."?track_id=".$object->track_id, $langs->trans("Delete"), $langs->trans("ConfirmDeleteTicket"), "confirm_delete_ticket", '', '', 1);
		}
		// Confirm reopen
		if ($action == 'reopen') {
			print $form->formconfirm($url_page_current.'?track_id='.$object->track_id, $langs->trans('ReOpen'), $langs->trans('ConfirmReOpenTicket'), 'confirm_reopen', '', '', 1);
		}
		// Confirmation status change
		if ($action == 'set_status') {
			$new_status = GETPOST('new_status');
			//var_dump($url_page_current . "?track_id=" . $object->track_id);
			print $form->formconfirm($url_page_current."?track_id=".$object->track_id."&new_status=".GETPOST('new_status'), $langs->trans("TicketChangeStatus"), $langs->trans("TicketConfirmChangeStatus", $langs->transnoentities($object->statuts_short[$new_status])), "confirm_set_status", '', '', 1);
		}

		// project info
		if ($projectid > 0) {
			$projectstat = new Project($db);
			if ($projectstat->fetch($projectid) > 0) {
				$projectstat->fetch_thirdparty();

				// To verify role of users
				//$userAccess = $object->restrictedProjectArea($user,'read');
				$userWrite = $projectstat->restrictedProjectArea($user, 'write');
				//$userDelete = $object->restrictedProjectArea($user,'delete');
				//print "userAccess=".$userAccess." userWrite=".$userWrite." userDelete=".$userDelete;

				$head = project_prepare_head($projectstat);

				print dol_get_fiche_head($head, 'ticket', $langs->trans("Project"), 0, ($projectstat->public ? 'projectpub' : 'project'));

				print '<table class="border centpercent">';

				$linkback = '<a href="'.DOL_URL_ROOT.'/projet/list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';

				// Ref
				print '<tr><td width="30%">'.$langs->trans('Ref').'</td><td colspan="3">';
				// Define a complementary filter for search of next/prev ref.
				if (empty($user->rights->projet->all->lire)) {
					$objectsListId = $projectstat->getProjectsAuthorizedForUser($user, $mine, 0);
					$projectstat->next_prev_filter = "rowid IN (".$db->sanitize(count($objectsListId) ? join(',', array_keys($objectsListId)) : '0').")";
				}
				print $form->showrefnav($projectstat, 'ref', $linkback, 1, 'ref', 'ref', '');
				print '</td></tr>';

				// Label
				print '<tr><td>'.$langs->trans("Label").'</td><td>'.$projectstat->title.'</td></tr>';

				// Customer
				print "<tr><td>".$langs->trans("ThirdParty")."</td>";
				print '<td colspan="3">';
				if ($projectstat->thirdparty->id > 0) {
					print $projectstat->thirdparty->getNomUrl(1);
				} else {
					print '&nbsp;';
				}

				print '</td></tr>';

				// Visibility
				print '<tr><td>'.$langs->trans("Visibility").'</td><td>';
				if ($projectstat->public) {
					print $langs->trans('SharedProject');
				} else {
					print $langs->trans('PrivateProject');
				}

				print '</td></tr>';

				// Statut
				print '<tr><td>'.$langs->trans("Status").'</td><td>'.$projectstat->getLibStatut(4).'</td></tr>';

				print "</table>";

				print dol_get_fiche_end();
			} else {
				print "ErrorRecordNotFound";
			}
		} elseif ($socid > 0) {
			$object->fetch_thirdparty();
			$head = societe_prepare_head($object->thirdparty);

			print dol_get_fiche_head($head, 'ticket', $langs->trans("ThirdParty"), 0, 'company');

			dol_banner_tab($object->thirdparty, 'socid', '', ($user->socid ? 0 : 1), 'rowid', 'nom');

			print dol_get_fiche_end();
		}

		if (!$user->socid && getDolGlobalString('TICKET_LIMIT_VIEW_ASSIGNED_ONLY')) {
			$object->next_prev_filter = "te.fk_user_assign = ".((int) $user->id);
		} elseif ($user->socid > 0) {
			$object->next_prev_filter = "te.fk_soc = ".((int) $user->socid);
		}

		$head = ticket_prepare_head($object);

		print dol_get_fiche_head($head, 'tabTicket', $langs->trans("Ticket"), -1, 'ticket');

		$morehtmlref = '<div class="refidno">';
		$morehtmlref .= $object->subject;
		// Author
		if ($object->fk_user_create > 0) {
			$morehtmlref .= '<br>'.$langs->trans("CreatedBy").' : ';

			$fuser = new User($db);
			$fuser->fetch($object->fk_user_create);
			$morehtmlref .= $fuser->getNomUrl(-1);
		} elseif (!empty($object->email_msgid)) {
			$morehtmlref .= '<br>'.$langs->trans("CreatedBy").' : ';
			$morehtmlref .= img_picto('', 'email', 'class="paddingrightonly"');
			$htmltooltip = $langs->trans("EmailMsgID").': '.$object->email_msgid;
			$htmltooltip .= '<br>'.$langs->trans("EmailDate").': '.dol_print_date($object->email_date, 'dayhour');
			$morehtmlref .= dol_escape_htmltag($object->origin_email).' <small class="hideonsmartphone opacitymedium">- '.$form->textwithpicto($langs->trans("CreatedByEmailCollector"), $htmltooltip, 1, 'help', '', 0, 3, 'tooltip').'</small>';
		} elseif (!empty($object->origin_email)) {
			$morehtmlref .= '<br>'.$langs->trans("CreatedBy").' : ';
			$morehtmlref .= img_picto('', 'email', 'class="paddingrightonly"');
			$htmltooptip = $langs->trans("IP").': '.$object->ip;
			$morehtmlref .= dol_escape_htmltag($object->origin_email).' <small class="hideonsmartphone opacitymedium">- '.$form->textwithpicto($langs->trans("CreatedByPublicPortal"), $htmltooptip, 1, 'help', '', 0, 3, 'tooltip').'</small>';
		}

		$permissiontoedit = $object->status < 8 && !$user->socid && $user->rights->ticket->write;
		//$permissiontoedit = 0;

		// Thirdparty
		if (isModEnabled("societe")) {
			$morehtmlref .= '<br>';
			$morehtmlref .= img_picto($langs->trans("ThirdParty"), 'company', 'class="pictofixedwidth"');
			if ($action != 'editcustomer' && $permissiontoedit) {
				$morehtmlref .= '<a class="editfielda" href="'.$url_page_current.'?action=editcustomer&token='.newToken().'&track_id='.$object->track_id.'">'.img_edit($langs->transnoentitiesnoconv('SetThirdParty'), 0).'</a> ';
			}
			$morehtmlref .= $form->form_thirdparty($url_page_current.'?track_id='.$object->track_id, $object->socid, $action == 'editcustomer' ? 'editcustomer' : 'none', '', 1, 0, 0, array(), 1);
			if (!empty($object->socid)) {
				$morehtmlref .= ' - <a href="'.DOL_URL_ROOT.'/ticket/list.php?socid='.$object->socid.'&sortfield=t.datec&sortorder=desc">'.img_picto($langs->trans("Tickets"), 'ticket', 'class="pictofixedwidth"').' '.$langs->trans("TicketHistory").'</a>';
			}
		}

		// Project
		if (isModEnabled('project')) {
			$langs->load("projects");
			$morehtmlref .= '<br>';
			if ($permissiontoedit) {
				$object->fetch_project();
				$morehtmlref .= img_picto($langs->trans("Project"), 'project'.((is_object($object->project) && $object->project->public) ? 'pub' : ''), 'class="pictofixedwidth"');
				if ($action != 'classify') {
					$morehtmlref .= '<a class="editfielda" href="'.$_SERVER['PHP_SELF'].'?action=classify&token='.newToken().'&id='.$object->id.'">'.img_edit($langs->transnoentitiesnoconv('SetProject')).'</a> ';
				}
				$morehtmlref .= $form->form_project($_SERVER['PHP_SELF'].'?id='.$object->id, $object->socid, $object->fk_project, ($action == 'classify' ? 'projectid' : 'none'), 0, 0, 0, 1, '', 'maxwidth300');
			} else {
				if (!empty($object->fk_project)) {
					$object->fetch_project();
					$morehtmlref .= $object->project->getNomUrl(1);
					if ($object->project->title) {
						$morehtmlref .= '<span class="opacitymedium"> - '.dol_escape_htmltag($object->project->title).'</span>';
					}
				}
			}
		}

		$morehtmlref .= '</div>';

		$linkback = '<a href="'.DOL_URL_ROOT.'/ticket/list.php?restore_lastsearch_values=1"><strong>'.$langs->trans("BackToList").'</strong></a> ';

		dol_banner_tab($object, 'ref', $linkback, ($user->socid ? 0 : 1), 'ref', 'ref', $morehtmlref);

		print '<div class="fichecenter">';
		print '<div class="fichehalfleft">';
		print '<div class="underbanner clearboth"></div>';

		print '<table class="border tableforfield centpercent">';

		// Track ID
		print '<tr><td class="titlefield">'.$langs->trans("TicketTrackId").'</td><td>';
		if (!empty($object->track_id)) {
			if (empty($object->ref)) {
				$object->ref = $object->id;
				print $form->showrefnav($object, 'id', $linkback, 1, 'rowid', 'track_id');
			} else {
				print $object->track_id;
			}
		} else {
			print $langs->trans('None');
		}
		print '</td></tr>';

		// Subject
		print '<tr><td>';
		print $form->editfieldkey("Subject", 'subject', $object->subject, $object, $user->rights->ticket->write && !$user->socid, 'string');
		print '</td><td>';
		print $form->editfieldval("Subject", 'subject', $object->subject, $object, $user->rights->ticket->write && !$user->socid, 'string');
		print '</td></tr>';

		// Creation date
		print '<tr><td>'.$langs->trans("DateCreation").'</td><td>';
		print dol_print_date($object->datec, 'dayhour', 'tzuser');
		print '<span class="opacitymedium"> - '.$langs->trans("TimeElapsedSince").': <i>'.convertSecondToTime(roundUpToNextMultiple($now - $object->datec, 60)).'</i></span>';
		print '</td></tr>';

		// Origin
		/*
		if ($object->email_msgid) {
			$texttoshow = $langs->trans("CreatedByEmailCollector");
		} elseif ($object->origin_email) {
			$texttoshow = $langs->trans("FromPublicEmail");
		}
		if ($texttoshow) {
			print '<tr><td class="titlefield fieldname_email_origin">';
			print $langs->trans("Origin");
			print '</td>';
			print '<td class="valuefield fieldname_email_origin">';
			print $texttoshow;
			print '</td></tr>';
		}
		*/

		// Read date
		print '<tr><td>'.$langs->trans("TicketReadOn").'</td><td>';
		if (!empty($object->date_read)) {
			print dol_print_date($object->date_read, 'dayhour', 'tzuser');
			print '<span class="opacitymedium"> - '.$langs->trans("TicketTimeElapsedBeforeSince").': <i>'.convertSecondToTime(roundUpToNextMultiple($object->date_read - $object->datec, 60)).'</i>';
			print ' / <i>'.convertSecondToTime(roundUpToNextMultiple($now - $object->date_read, 60)).'</i></span>';
		}
		print '</td></tr>';

		// Close date
		print '<tr><td>'.$langs->trans("TicketCloseOn").'</td><td>';
		if (!empty($object->date_close)) {
			print dol_print_date($object->date_close, 'dayhour', 'tzuser');
		}
		print '</td></tr>';

		// User assigned
		print '<tr><td>';
		print '<table class="nobordernopadding" width="100%"><tr><td class="nowrap">';
		print $langs->trans("AssignedTo");
		if (isset($object->status) && $object->status < $object::STATUS_CLOSED && GETPOST('set', 'alpha') != "assign_ticket" && $user->rights->ticket->manage) {
			print '</td><td class="right"><a class="editfielda" href="'.$url_page_current.'?track_id='.urlencode($object->track_id).'&set=assign_ticket">'.img_edit($langs->trans('Modify'), '').'</a>';
		}
		print '</td></tr></table>';
		print '</td><td>';
		if (GETPOST('set', 'alpha') != "assign_ticket" && $object->fk_user_assign > 0) {
			$userstat->fetch($object->fk_user_assign);
			print $userstat->getNomUrl(-1);
		}

		// Show user list to assignate one if status is "read"
		if (GETPOST('set', 'alpha') == "assign_ticket" && $object->status < 8 && !$user->socid && $user->rights->ticket->write) {
			print '<form method="post" name="ticket" enctype="multipart/form-data" action="'.$url_page_current.'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="assign_user">';
			print '<input type="hidden" name="track_id" value="'.$object->track_id.'">';
			//print '<label for="fk_user_assign">'.$langs->trans("AssignUser").'</label> ';
			print $form->select_dolusers(empty($object->fk_user_assign)?$user->id:$object->fk_user_assign, 'fk_user_assign', 1);
			print ' <input type="submit" class="button small" name="btn_assign_user" value="'.$langs->trans("Validate").'" />';
			print '</form>';
		}
		print '</td></tr>';

		// Progression
		print '<tr><td>';
		print '<table class="nobordernopadding centpercent"><tr><td class="nowrap">';
		print $langs->trans('Progression').'</td><td class="left">';
		print '</td>';
		if ($action != 'progression' && isset($object->status) && $object->status < $object::STATUS_CLOSED && !$user->socid) {
			print '<td class="right"><a class="editfielda" href="'.$url_page_current.'?action=progression&token='.newToken().'&track_id='.urlencode($object->track_id).'">'.img_edit($langs->trans('Modify')).'</a></td>';
		}
		print '</tr></table>';
		print '</td><td>';
		if ($user->rights->ticket->write && $action == 'progression') {
			print '<form action="'.$url_page_current.'" method="post">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="track_id" value="'.$track_id.'">';
			print '<input type="hidden" name="action" value="set_progression">';
			print '<input type="text" class="flat width75" name="progress" value="'.$object->progress.'">';
			print ' <input type="submit" class="button button-edit small" value="'.$langs->trans('Modify').'">';
			print '</form>';
		} else {
			print($object->progress > 0 ? $object->progress : '0').'%';
		}
		print '</td>';
		print '</tr>';

		// Timing (Duration sum of linked fichinter)
		if (isModEnabled('ficheinter')) {
			$object->fetchObjectLinked();
			$num = count($object->linkedObjects);
			$timing = 0;
			$foundinter = 0;
			if ($num) {
				foreach ($object->linkedObjects as $objecttype => $objects) {
					if ($objecttype == "fichinter") {
						foreach ($objects as $fichinter) {
							$foundinter++;
							$timing += $fichinter->duration;
						}
					}
				}
			}
			print '<tr><td>';
			print $form->textwithpicto($langs->trans("TicketDurationAuto"), $langs->trans("TicketDurationAutoInfos"), 1);
			print '</td><td>';
			print $foundinter ? convertSecondToTime($timing, 'all', getDolGlobalString('MAIN_DURATION_OF_WORKDAY')) : '';
			print '</td></tr>';
		}

		// Other attributes
		include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';

		print '</table>';


		// Fin colonne gauche et début colonne droite
		print '</div><div class="fichehalfright">';


		print '<form method="post" name="formticketproperties" action="'.$url_page_current.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="change_property">';
		print '<input type="hidden" name="track_id" value="'.$track_id.'">';
		print '<input type="hidden" name="trackid" value="'.$trackid.'">';

		print '<div class="underbanner clearboth"></div>';

		// Categories
		if (isModEnabled('categorie')) {
			print '<table class="border centpercent tableforfield">';
			print '<tr>';
			print '<td class="valignmiddle titlefield">';
			print '<table class="nobordernopadding centpercent"><tr><td class="titlefield">';
			print $langs->trans("Categories");
			if ($action != 'categories' && !$user->socid) {
				print '<td class="right"><a class="editfielda" href="'.$url_page_current.'?action=categories&amp;track_id='.$object->track_id.'">'.img_edit($langs->trans('Modify')).'</a></td>';
			}
			print '</table>';
			print '</td>';

			if ($user->rights->ticket->write && $action == 'categories') {
				$cate_arbo = $form->select_all_categories(Categorie::TYPE_TICKET, '', 'parent', 64, 0, 1);
				if (is_array($cate_arbo)) {
					// Categories
					print '<td colspan="3">';
					print '<form action="'.$url_page_current.'" method="post">';
					print '<input type="hidden" name="token" value="'.newToken().'">';
					print '<input type="hidden" name="track_id" value="'.$track_id.'">';
					print '<input type="hidden" name="action" value="set_categories">';

					$category = new Categorie($db);
					$cats = $category->containing($object->id, 'ticket');
					$arrayselected = array();
					foreach ($cats as $cat) {
						$arrayselected[] = $cat->id;
					}

					print img_picto('', 'category', 'class="pictofixedwidth"').$form->multiselectarray('categories', $cate_arbo, $arrayselected, '', 0, 'quatrevingtpercent widthcentpercentminusx', 0, 0);
					print '<input type="submit" class="button button-edit small" value="'.$langs->trans('Save').'">';
					print '</form>';
					print "</td>";
				}
			} else {
				print '<td colspan="3">';
				print $form->showCategories($object->id, Categorie::TYPE_TICKET, 1);
				print "</td></tr>";
			}

			print '</table>';
		}

		// View Original message
		$actionobject->viewTicketOriginalMessage($user, $action, $object);

		// Classification of ticket
		print '<div class="div-table-responsive-no-min">'; // You can use div-table-responsive-no-min if you dont need reserved height for your table
		print '<table class="noborder tableforfield centpercent margintable">';
		print '<tr class="liste_titre">';
		print '<td>';
		print $langs->trans('TicketProperties');
		print '</td>';
		print '<td>';
		if (GETPOST('set', 'alpha') == 'properties' && $user->rights->ticket->write) {
			print '<input type="submit" class="button small" name="btn_update_ticket_prop" value="'.$langs->trans("Modify").'" />';
		} else {
			//    Button to edit Properties
			if (isset($object->status) && $object->status < $object::STATUS_NEED_MORE_INFO && $user->rights->ticket->write) {
				print ' <a class="editfielda" href="card.php?track_id='.$object->track_id.'&set=properties">'.img_edit($langs->trans('Modify')).'</a>';
			}
		}
		print '</td>';
		print '</tr>';

		if (GETPOST('set', 'alpha') == 'properties' && $user->rights->ticket->write) {
			print '<tr>';
			// Type
			print '<td class="titlefield">';
			print $langs->trans('Type');
			print '</td><td>';
			$formticket->selectTypesTickets($object->type_code, 'update_value_type', '', 2);
			print '</td>';
			print '</tr>';
			// Group
			print '<tr>';
			print '<td>';
			print $langs->trans('TicketCategory');
			print '</td><td>';
			$formticket->selectGroupTickets($object->category_code, 'update_value_category', '', 2, 0, 0, 0, 'maxwidth500 widthcentpercentminusxx');
			print '</td>';
			print '</tr>';
			// Severity
			print '<tr>';
			print '<td>';
			print $langs->trans('TicketSeverity');
			print '</td><td>';
			$formticket->selectSeveritiesTickets($object->severity_code, 'update_value_severity', '', 2);
			print '</td>';
			print '</tr>';
		} else {
			// Type
			print '<tr><td class="titlefield">'.$langs->trans("Type").'</td><td>';
			if (!empty($object->type_code)) {
				print $langs->getLabelFromKey($db, 'TicketTypeShort'.$object->type_code, 'c_ticket_type', 'code', 'label', $object->type_code);
			}
			print '</td></tr>';
			// Group
			$s = '';
			if (!empty($object->category_code)) {
				$s = $langs->getLabelFromKey($db, 'TicketCategoryShort'.$object->category_code, 'c_ticket_category', 'code', 'label', $object->category_code);
			}
			print '<tr><td>'.$langs->trans("TicketCategory").'</td><td class="tdoverflowmax200" title="'.dol_escape_htmltag($s).'">';
			print dol_escape_htmltag($s);
			print '</td></tr>';
			// Severity
			print '<tr><td>'.$langs->trans("TicketSeverity").'</td><td>';
			if (!empty($object->severity_code)) {
				print $langs->getLabelFromKey($db, 'TicketSeverityShort'.$object->severity_code, 'c_ticket_severity', 'code', 'label', $object->severity_code);
			}
			print '</td></tr>';
		}
		print '</table>'; // End table actions
		print '</div>';

		print '</form>';

		// Display navbar with links to change ticket status
		print '<!-- navbar with status -->';
		if (!$user->socid && $user->rights->ticket->write && isset($object->status) && $object->status < $object::STATUS_CLOSED && GETPOST('set') !== 'properties') {
			$actionobject->viewStatusActions($object);
		}


		if (getDolGlobalString('MAIN_DISABLE_CONTACTS_TAB')) {
			print load_fiche_titre($langs->trans('Contacts'), '', 'title_companies.png');

			print '<div class="div-table-responsive-no-min">';
			print '<div class="tagtable centpercent noborder allwidth">';

			print '<div class="tagtr liste_titre">';
			print '<div class="tagtd">'.$langs->trans("Source").'</div>
			<div class="tagtd">' . $langs->trans("Company").'</div>
			<div class="tagtd">' . $langs->trans("Contacts").'</div>
			<div class="tagtd">' . $langs->trans("ContactType").'</div>
			<div class="tagtd">' . $langs->trans("Phone").'</div>
			<div class="tagtd center">' . $langs->trans("Status").'</div>';
			print '</div><!-- tagtr -->';

			// Contact list
			$companystatic = new Societe($db);
			$contactstatic = new Contact($db);
			$userstatic = new User($db);
			$var = false;
			foreach (array('internal', 'external') as $source) {
				$tmpobject = $object;
				$tab = $tmpobject->listeContact(-1, $source);
				$num = count($tab);
				$i = 0;
				while ($i < $num) {
					$var = !$var;
					print '<div class="tagtr '.($var ? 'pair' : 'impair').'">';

					print '<div class="tagtd left">';
					if ($tab[$i]['source'] == 'internal') {
						echo $langs->trans("User");
					}

					if ($tab[$i]['source'] == 'external') {
						echo $langs->trans("ThirdPartyContact");
					}

					print '</div>';
					print '<div class="tagtd left">';

					if ($tab[$i]['socid'] > 0) {
						$companystatic->fetch($tab[$i]['socid']);
						echo $companystatic->getNomUrl(-1);
					}
					if ($tab[$i]['socid'] < 0) {
						echo getDolGlobalString('MAIN_INFO_SOCIETE_NOM');
					}
					if (!$tab[$i]['socid']) {
						echo '&nbsp;';
					}
					print '</div>';

					print '<div class="tagtd">';
					if ($tab[$i]['source'] == 'internal') {
						if ($userstatic->fetch($tab[$i]['id'])) {
							print $userstatic->getNomUrl(-1);
						}
					}
					if ($tab[$i]['source'] == 'external') {
						if ($contactstatic->fetch($tab[$i]['id'])) {
							print $contactstatic->getNomUrl(-1);
						}
					}
					print ' </div>
					<div class="tagtd">' . $tab[$i]['libelle'].'</div>';

					print '<div class="tagtd">';

					print dol_print_phone($tab[$i]['phone'], '', '', '', 'AC_TEL').'<br>';

					if (!empty($tab[$i]['phone_perso'])) {
						//print img_picto($langs->trans('PhonePerso'),'object_phoning.png','',0,0,0).' ';
						print '<br>'.dol_print_phone($tab[$i]['phone_perso'], '', '', '', 'AC_TEL').'<br>';
					}
					if (!empty($tab[$i]['phone_mobile'])) {
						//print img_picto($langs->trans('PhoneMobile'),'object_phoning.png','',0,0,0).' ';
						print dol_print_phone($tab[$i]['phone_mobile'], '', '', '', 'AC_TEL').'<br>';
					}
					print '</div>';

					print '<div class="tagtd center">';
					if ($object->status >= 0) {
						echo '<a href="contact.php?track_id='.$object->track_id.'&amp;action=swapstatut&amp;ligne='.$tab[$i]['rowid'].'">';
					}

					if ($tab[$i]['source'] == 'internal') {
						$userstatic->id = $tab[$i]['id'];
						$userstatic->lastname = $tab[$i]['lastname'];
						$userstatic->firstname = $tab[$i]['firstname'];
						echo $userstatic->LibStatut($tab[$i]['statuscontact'], 3);
					}
					if ($tab[$i]['source'] == 'external') {
						$contactstatic->id = $tab[$i]['id'];
						$contactstatic->lastname = $tab[$i]['lastname'];
						$contactstatic->firstname = $tab[$i]['firstname'];
						echo $contactstatic->LibStatut($tab[$i]['statuscontact'], 3);
					}
					if ($object->status >= 0) {
						echo '</a>';
					}

					print '</div>';

					print '</div><!-- tagtr -->';

					$i++;
				}
			}

			print '</div><!-- contact list -->';
			print '</div>';
		}

		print '</div></div>';
		print '<div class="clearboth"></div>';

		print dol_get_fiche_end();


		// Buttons for actions
		if ($action != 'presend' && $action != 'presend_addmessage' && $action != 'editline') {
			print '<div class="tabsAction">'."\n";
			$parameters = array();
			$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
			if ($reshook < 0) {
				setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
			}

			if (empty($reshook)) {
				// Email
				if (isset($object->status) && $object->status < Ticket::STATUS_CLOSED && $action != "presend" && $action != "presend_addmessage") {
					print dolGetButtonAction('', $langs->trans('SendMail'), 'default', $_SERVER["PHP_SELF"].'?action=presend_addmessage&send_email=1&private_message=0&mode=init&token='.newToken().'&track_id='.$object->track_id.'#formmailbeforetitle', '');
				}

				// Show link to add a message (if read and not closed)
				if (isset($object->status) && $object->status < Ticket::STATUS_CLOSED && $action != "presend" && $action != "presend_addmessage") {
					print dolGetButtonAction('', $langs->trans('TicketAddPrivateMessage'), 'default', $_SERVER["PHP_SELF"].'?action=presend_addmessage&mode=init&token='.newToken().'&track_id='.$object->track_id.'#formmailbeforetitle', '');
				}

				// Link to create an intervention
				// socid is needed otherwise fichinter ask it and forgot origin after form submit :\
				if (!$object->fk_soc && $user->hasRight("ficheinter", "creer")) {
					print dolGetButtonAction($langs->trans('UnableToCreateInterIfNoSocid'), $langs->trans('TicketAddIntervention'), 'default', $_SERVER['PHP_SELF']. '#', '', false);
				}
				if ($object->fk_soc > 0 && isset($object->status) && $object->status < Ticket::STATUS_CLOSED && $user->hasRight('ficheinter', 'creer')) {
					print dolGetButtonAction('', $langs->trans('TicketAddIntervention'), 'default', DOL_URL_ROOT.'/fichinter/card.php?action=create&token='.newToken().'&socid='. $object->fk_soc.'&origin=ticket_ticket&originid='. $object->id, '');
				}

				// Close ticket if statut is read
				if (isset($object->status) && $object->status > 0 && $object->status < Ticket::STATUS_CLOSED && $user->rights->ticket->write) {
					print dolGetButtonAction('', $langs->trans('CloseTicket'), 'default', $_SERVER["PHP_SELF"].'?action=close&token='.newToken().'&track_id='.$object->track_id, '');
				}

				// Abadon ticket if statut is read
				if (isset($object->status) && $object->status > 0 && $object->status < Ticket::STATUS_CLOSED && $user->rights->ticket->write) {
					print dolGetButtonAction('', $langs->trans('AbandonTicket'), 'default', $_SERVER["PHP_SELF"].'?action=abandon&token='.newToken().'&track_id='.$object->track_id, '');
				}

				// Re-open ticket
				if (!$user->socid && (isset($object->status) && ($object->status == Ticket::STATUS_CLOSED || $object->status == Ticket::STATUS_CANCELED)) && !$user->socid) {
					print dolGetButtonAction('', $langs->trans('ReOpen'), 'default', $_SERVER["PHP_SELF"].'?action=reopen&token='.newToken().'&track_id='.$object->track_id, '');
				}

				// Delete ticket
				if ($user->rights->ticket->delete && !$user->socid) {
					print dolGetButtonAction('', $langs->trans('Delete'), 'delete', $_SERVER["PHP_SELF"].'?action=delete&token='.newToken().'&track_id='.$object->track_id, '');
				}
			}
			print '</div>'."\n";
		} else {
			//print '<br>';
		}

		// Select mail models is same action as presend
		if (GETPOST('modelselected')) {
			$action = 'presend';
		}
		// Set $action to correct value for the case we used presend action to add a message
		if (GETPOSTISSET('actionbis') && $action == 'presend') {
			$action = 'presend_addmessage';
		}

		// add a message
		if ($action == 'presend' || $action == 'presend_addmessage') {
			if ($object->fk_soc > 0) {
				$object->fetch_thirdparty();
			}

			$outputlangs = $langs;
			$newlang = '';
			if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang) && GETPOST('lang_id', 'aZ09')) {
				$newlang = GETPOST('lang_id', 'aZ09');
			} elseif (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang) && is_object($object->thirdparty)) {
				$newlang = $object->thirdparty->default_lang;
			}
			if (!empty($newlang)) {
				$outputlangs = new Translate("", $conf);
				$outputlangs->setDefaultLang($newlang);
			}

			$arrayoffamiliestoexclude = array('objectamount');

			$action = 'add_message'; // action to use to post the message
			$modelmail = 'ticket_send';

			// Substitution array
			$morehtmlright = '';
			$help = "";
			$substitutionarray = getCommonSubstitutionArray($outputlangs, 0, $arrayoffamiliestoexclude, $object);
			$morehtmlright .= $form->textwithpicto('<span class="opacitymedium">'.$langs->trans("TicketMessageSubstitutionReplacedByGenericValues").'</span>', $help, 1, 'helpclickable', '', 0, 3, 'helpsubstitution');

			print '<div>';

			print '<div id="formmailbeforetitle" name="formmailbeforetitle"></div>';

			print load_fiche_titre($langs->trans('TicketAddMessage'), $morehtmlright, 'messages@ticket');

			print '<hr>';

			$formticket = new FormTicket($db);

			$formticket->action = $action;
			$formticket->track_id = $object->track_id;
			$formticket->ref = $object->ref;
			$formticket->id = $object->id;
			$formticket->trackid = 'tic'.$object->id;

			$formticket->withfile = 2;
			$formticket->withcancel = 1;
			$formticket->param = array('fk_user_create' => $user->id);
			$formticket->param['langsmodels'] = (empty($newlang) ? $langs->defaultlang : $newlang);

			// Tableau des parametres complementaires du post
			$formticket->param['models'] = $modelmail;
			$formticket->param['models_id'] = GETPOST('modelmailselected', 'int');
			//$formticket->param['socid']=$object->fk_soc;
			$formticket->param['returnurl'] = $_SERVER["PHP_SELF"].'?track_id='.$object->track_id;

			$formticket->withsubstit = 1;
			$formticket->substit = $substitutionarray;
			$formticket->backtopage = $backtopage;

			$formticket->showMessageForm('100%');
			print '</div>';
		}

		// Show messages on card (Note: this is a duplicate of the view Events/Agenda but on the main tab)
		if (getDolGlobalString('TICKET_SHOW_MESSAGES_ON_CARD')) {
			$param = '&id='.$object->id;
			if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) {
				$param .= '&contextpage='.$contextpage;
			}
			if ($limit > 0 && $limit != $conf->liste_limit) {
				$param .= '&limit='.$limit;
			}
			if ($actioncode) {
				$param .= '&actioncode='.urlencode($actioncode);
			}
			if ($search_agenda_label) {
				$param .= '&search_agenda_label='.urlencode($search_agenda_label);
			}

			$morehtmlright = '';

			$messagingUrl = DOL_URL_ROOT.'/ticket/agenda.php?track_id='.$object->track_id;
			$morehtmlright .= dolGetButtonTitle($langs->trans('MessageListViewType'), '', 'fa fa-bars imgforviewmode', $messagingUrl, '', 1);

			// Show link to add a message (if read and not closed)
			$btnstatus = $object->status < Ticket::STATUS_CLOSED && $action != "presend" && $action != "presend_addmessage" && $action != "add_message";
			$url = 'card.php?track_id='.$object->track_id.'&action=presend_addmessage&mode=init';
			$morehtmlright .= dolGetButtonTitle($langs->trans('TicketAddMessage'), '', 'fa fa-comment-dots', $url, 'add-new-ticket-title-button', $btnstatus);

			// Show link to add event (if read and not closed)
			$btnstatus = $object->status < Ticket::STATUS_CLOSED && $action != "presend" && $action != "presend_addmessage" && $action != "add_message";
			$url = dol_buildpath('/comm/action/card.php', 1).'?action=create&datep='.date('YmdHi').'&origin=ticket&originid='.$object->id.'&projectid='.$object->fk_project.'&backtopage='.urlencode($_SERVER["PHP_SELF"].'?track_id='.$object->track_id);
			$morehtmlright .= dolGetButtonTitle($langs->trans('AddAction'), '', 'fa fa-plus-circle', $url, 'add-new-ticket-even-button', $btnstatus);

			print_barre_liste($langs->trans("ActionsOnTicket"), 0, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', 0, -1, '', 0, $morehtmlright, '', 0, 1, 1);

			// List of all actions
			$filters = array();
			$filters['search_agenda_label'] = $search_agenda_label;
			$filters['search_rowid'] = $search_rowid;

			show_actions_messaging($conf, $langs, $db, $object, null, 0, $actioncode, '', $filters, $sortfield, $sortorder);
		}

		if ($action != 'presend' && $action != 'presend_addmessage' && $action != 'add_message') {
			print '<div class="fichecenter"><div class="fichehalfleft">';
			print '<a name="builddoc"></a>'; // ancre

			// Show links to link elements
			$linktoelem = $form->showLinkToObjectBlock($object, null, array('ticket'));
			$somethingshown = $form->showLinkedObjectBlock($object, $linktoelem);

			// Show direct link to public interface
			print '<br><!-- Link to public interface -->'."\n";
			print showDirectPublicLink($object).'<br>';
			print '</div>';

			if (getDolGlobalString('TICKET_SHOW_MESSAGES_ON_CARD')) {
				print '<div class="fichehalfright">';

				$MAXEVENT = 10;

				$morehtmlcenter = '<div class="nowraponall">';
				$morehtmlcenter .= dolGetButtonTitle($langs->trans('FullConversation'), '', 'fa fa-comments imgforviewmode', DOL_URL_ROOT.'/ticket/messaging.php?id='.$object->id);
				$morehtmlcenter .= ' ';
				$morehtmlcenter .= dolGetButtonTitle($langs->trans('FullList'), '', 'fa fa-bars imgforviewmode', DOL_URL_ROOT.'/ticket/agenda.php?id='.$object->id);
				$morehtmlcenter .= '</div>';

				// List of actions on element
				include_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
				$formactions = new FormActions($db);
				$somethingshown = $formactions->showactions($object, 'ticket', $socid, 1, 'listactions', $MAXEVENT, '', $morehtmlcenter);

				print '</div>';
			}

			print '</div>';
		}
	}
}

// End of page
llxFooter();
$db->close();
