<?php
/* Copyright (C) 2015       Jean-François Ferry     <jfefe@aternatik.fr>
 * Copyright (C) 2019-2020  Frédéric France         <frederic.france@netlogic.fr>
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
 * \file    htdocs/zapier/class/api_zapier.class.php
 * \ingroup zapier
 * \brief   File for API management of Zapier hooks.
 */

use Luracast\Restler\RestException;

require_once DOL_DOCUMENT_ROOT.'/zapier/class/hook.class.php';


/**
 * API class for zapier hook
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class Zapier extends DolibarrApi
{
	/**
	 * @var array   $FIELDS     Mandatory fields, checked when create and update object
	 */
	public static $FIELDS = array(
		'url',
	);


	/**
	 * @var Hook $hook {@type Hook}
	 */
	public $hook;

	/**
	 * Constructor
	 *
	 * @url     GET /
	 *
	 */
	public function __construct()
	{
		global $db, $conf;
		$this->db = $db;
		$this->hook = new Hook($this->db);
	}

	/**
	 * Get properties of a hook object
	 *
	 * Return an array with hook informations
	 *
	 * @param   int             $id 	ID of hook
	 * @return  Object              	Object with cleaned properties
	 *
	 * @url GET /hooks/{id}
	 * @throws  RestException
	 */
	public function get($id)
	{
		if (!DolibarrApiAccess::$user->rights->zapier->read) {
			throw new RestException(401);
		}

		$result = $this->hook->fetch($id);
		if (!$result) {
			throw new RestException(404, 'Hook not found');
		}

		if (!DolibarrApi::_checkAccessToResource('hook', $this->hook->id)) {
			throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
		}

		return $this->_cleanObjectDatas($this->hook);
	}

	/**
	 * Get list of possibles choices for module
	 *
	 * Return an array with hook informations
	 *
	 * @return  array     data
	 *
	 * @url GET /getmoduleschoices/
	 * @throws  RestException
	 */
	public function getModulesChoices()
	{
		if (!DolibarrApiAccess::$user->rights->zapier->read) {
			throw new RestException(401);
		}

		$arraychoices = array(
			'invoices' => 'Invoices',
			'orders' => 'Orders',
			'thirdparties' => 'Thirparties',
			'contacts' => 'Contacts',
			'users' => 'Users',
		);
		// $result = $this->hook->fetch($id);
		// if (! $result ) {
		//     throw new RestException(404, 'Hook not found');
		// }

		// if (! DolibarrApi::_checkAccessToResource('hook', $this->hook->id)) {
		//     throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
		// }

		return $arraychoices;
	}

	/**
	 * List hooks
	 *
	 * Get a list of hooks
	 *
	 * @param string           $sortfield           Sort field
	 * @param string           $sortorder           Sort order
	 * @param int              $limit               Limit for list
	 * @param int              $page                Page number
	 * @param string           $sqlfilters          Other criteria to filter answers separated by a comma. Syntax example "(t.ref:like:'SO-%') and (t.date_creation:<:'20160101')"
	 * @return  array                               Array of order objects
	 *
	 * @throws RestException
	 *
	 * @url GET /hooks/
	 */
	public function index($sortfield = "t.rowid", $sortorder = 'ASC', $limit = 100, $page = 0, $sqlfilters = '')
	{
		global $db, $conf;

		if (!DolibarrApiAccess::$user->rights->zapier->read) {
			throw new RestException(401);
		}

		$obj_ret = array();

		$socid = DolibarrApiAccess::$user->socid ? DolibarrApiAccess::$user->socid : '';

		// Set to 1 if there is a field socid in table of object
		$restrictonsocid = 0;

		// If the internal user must only see his customers, force searching by him
		$search_sale = 0;
		if ($restrictonsocid && !DolibarrApiAccess::$user->rights->societe->client->voir && !$socid) {
			$search_sale = DolibarrApiAccess::$user->id;
		}

		$sql = "SELECT t.rowid";
		if ($restrictonsocid && (!DolibarrApiAccess::$user->rights->societe->client->voir && !$socid) || $search_sale > 0) {
			// We need these fields in order to filter by sale (including the case where the user can only see his prospects)
			$sql .= ", sc.fk_soc, sc.fk_user";
		}
		$sql .= " FROM ".MAIN_DB_PREFIX."hook_mytable as t";

		if ($restrictonsocid && (!DolibarrApiAccess::$user->rights->societe->client->voir && !$socid) || $search_sale > 0) {
			$sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc"; // We need this table joined to the select in order to filter by sale
		}
		$sql .= " WHERE 1 = 1";

		// Example of use $mode
		//if ($mode == 1) $sql.= " AND s.client IN (1, 3)";
		//if ($mode == 2) $sql.= " AND s.client IN (2, 3)";

		$tmpobject = new Hook($this->db);
		if ($tmpobject->ismultientitymanaged) {
			$sql .= ' AND t.entity IN ('.getEntity('hook').')';
		}
		if ($restrictonsocid && (!DolibarrApiAccess::$user->rights->societe->client->voir && !$socid) || $search_sale > 0) {
			$sql .= " AND t.fk_soc = sc.fk_soc";
		}
		if ($restrictonsocid && $socid) {
			$sql .= " AND t.fk_soc = ".((int) $socid);
		}
		if ($restrictonsocid && $search_sale > 0) {
			// Join for the needed table to filter by sale
			$sql .= " AND t.rowid = sc.fk_soc";
		}
		// Insert sale filter
		if ($restrictonsocid && $search_sale > 0) {
			$sql .= " AND sc.fk_user = ".((int) $search_sale);
		}
		if ($sqlfilters) {
			$errormessage = '';
			$sql .= forgeSQLFromUniversalSearchCriteria($sqlfilters, $errormessage);
			if ($errormessage) {
				throw new RestException(400, 'Error when validating parameter sqlfilters -> '.$errormessage);
			}
		}

		$sql .= $this->db->order($sortfield, $sortorder);
		if ($limit) {
			if ($page < 0) {
				$page = 0;
			}
			$offset = $limit * $page;

			$sql .= $this->db->plimit($limit + 1, $offset);
		}

		$result = $this->db->query($sql);
		$i = 0;
		if ($result) {
			$num = $this->db->num_rows($result);
			while ($i < $num) {
				$obj = $this->db->fetch_object($result);
				$hook_static = new Hook($this->db);
				if ($hook_static->fetch($obj->rowid)) {
					$obj_ret[] = $this->_cleanObjectDatas($hook_static);
				}
				$i++;
			}
		} else {
			throw new RestException(503, 'Error when retrieve hook list');
		}
		if (!count($obj_ret)) {
			throw new RestException(404, 'No hook found');
		}
		return $obj_ret;
	}

	/**
	 * Create hook object
	 *
	 * @param array $request_data   Request datas
	 * @return array  ID of hook
	 *
	 * @url	POST /hook/
	 */
	public function post($request_data = null)
	{
		if (!DolibarrApiAccess::$user->rights->zapier->write) {
			throw new RestException(401);
		}

		// Check mandatory fields
		$fields = array(
			'url',
		);
		dol_syslog("API Zapier create hook receive : ".print_r($request_data, true), LOG_DEBUG);
		$result = $this->validate($request_data, $fields);

		foreach ($request_data as $field => $value) {
			$this->hook->$field = $value;
		}
		$this->hook->fk_user = DolibarrApiAccess::$user->id;
		// we create the hook into database
		if (!$this->hook->create(DolibarrApiAccess::$user)) {
			throw new RestException(500, "Error creating Hook", array_merge(array($this->hook->error), $this->hook->errors));
		}
		return array(
			'id' => $this->hook->id,
		);
	}

	// /**
	//  * Update hook
	//  *
	//  * @param int   $id             Id of hook to update
	//  * @param array $request_data   Datas
	//  * @return int
	//  *
	//  * @url	PUT /hooks/{id}
	//  */
	/*public function put($id, $request_data = null)
	{
		if (! DolibarrApiAccess::$user->rights->zapier->write) {
			throw new RestException(401);
		}

		$result = $this->hook->fetch($id);
		if( ! $result ) {
			throw new RestException(404, 'Hook not found');
		}

		if( ! DolibarrApi::_checkAccessToResource('hook', $this->hook->id)) {
			throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
		}

		foreach($request_data as $field => $value) {
			if ($field == 'id') {
				continue;
			}
			$this->hook->$field = $value;
		}

		if ($this->hook->update(DolibarrApiAccess::$user) > 0) {
			return $this->get($id);
		} else {
			throw new RestException(500, $this->hook->error);
		}
	}*/

	/**
	 * Delete hook
	 *
	 * @param   int     $id   Hook ID
	 * @return  array
	 *
	 * @url DELETE /hook/{id}
	 */
	public function delete($id)
	{
		if (!DolibarrApiAccess::$user->rights->zapier->delete) {
			throw new RestException(401);
		}

		$result = $this->hook->fetch($id);
		if (!$result) {
			throw new RestException(404, 'Hook not found');
		}

		if (!DolibarrApi::_checkAccessToResource('hook', $this->hook->id)) {
			throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
		}

		if (!$this->hook->delete(DolibarrApiAccess::$user)) {
			throw new RestException(500, 'Error when deleting Hook : '.$this->hook->error);
		}

		return array(
			'success' => array(
				'code' => 200,
				'message' => 'Hook deleted'
			)
		);
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 * Clean sensible object datas
	 *
	 * @param   Object  $object     Object to clean
	 * @return  Object              Object with cleaned properties
	 */
	public function _cleanObjectDatas($object)
	{
		// phpcs:disable
		$object = parent::_cleanObjectDatas($object);

		return $object;
	}

	/**
	 * Validate fields before create or update object
	 *
	 * @param   array       $data       Array of data to validate
	 * @param   array       $fields     Array of fields needed
	 * @return  array
	 *
	 * @throws  RestException
	 */
	private function validate($data, $fields)
	{
		$hook = array();
		foreach ($fields as $field) {
			if (!isset($data[$field])) {
				throw new RestException(400, $field." field missing");
			}
			$hook[$field] = $data[$field];
		}
		return $hook;
	}
}
