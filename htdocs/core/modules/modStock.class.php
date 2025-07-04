<?php
/* Copyright (C) 2003-2006 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2008 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2009 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2012	   Juanjo Menent        <jmenent@2byte.es>
 * Copyright (C) 2021	   Ferran Marcet        <fmarcet@2byte.es>
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
 *	\defgroup   	stock     Module stocks
 *	\brief      	Module pour gerer la tenue de stocks produits
 *	\file       htdocs/core/modules/modStock.class.php
 *	\ingroup    stock
 *	\brief      Description and activation file for the module Stock
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';


/**
 *	Class to describe and enable module Stock
 */
class modStock extends DolibarrModules
{
	/**
	 *   Constructor. Define names, constants, directories, boxes, permissions
	 *
	 *   @param      DoliDB		$db      Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs;

		$this->db = $db;
		$this->numero = 52;

		$this->family = "products";
		$this->module_position = '39';
		// Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Gestion des stocks";

		// Possible values for version are: 'development', 'experimental', 'dolibarr' or version
		$this->version = 'dolibarr';

		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'stock';

		// Data directories to create when module is enabled
		$this->dirs = array("/stock/temp");

		$this->config_page_url = array("stock.php");

		// Dependencies
		$this->hidden = false; // A condition to hide module
		$this->depends = array("modProduct"); // List of module class names as string that must be enabled if this module is enabled
		$this->requiredby = array("modProductBatch"); // List of module ids to disable if this one is disabled
		$this->conflictwith = array(); // List of module class names as string this module is in conflict with
		$this->phpmin = array(7, 0); // Minimum version of PHP required by module
		$this->langfiles = array("stocks");

		// Constants
		$this->const = array();
		$r = 0;

		$this->const[$r] = array('STOCK_ALLOW_NEGATIVE_TRANSFER', 'chaine', '1', '', 1);

		$r++;
		$this->const[$r][0] = "STOCK_ADDON_PDF";
		$this->const[$r][1] = "chaine";
		$this->const[$r][2] = "standard";
		$this->const[$r][3] = 'Name of PDF model of stock';
		$this->const[$r][4] = 0;

		$r++;
		$this->const[$r][0] = "MOUVEMENT_ADDON_PDF";
		$this->const[$r][1] = "chaine";
		$this->const[$r][2] = "stdmovement";
		$this->const[$r][3] = 'Name of PDF model of stock movement';
		$this->const[$r][4] = 0;

		$r++;
		$this->const[$r][0] = "STOCK_ADDON_PDF_ODT_PATH";
		$this->const[$r][1] = "chaine";
		$this->const[$r][2] = "DOL_DATA_ROOT/doctemplates/stocks";
		$this->const[$r][3] = "";
		$this->const[$r][4] = 0;

		$r++;
		$this->const[$r][0] = "MOUVEMENT_ADDON_PDF_ODT_PATH";
		$this->const[$r][1] = "chaine";
		$this->const[$r][2] = "DOL_DATA_ROOT/doctemplates/stocks/mouvements";
		$this->const[$r][3] = "";
		$this->const[$r][4] = 0;

		// Boxes
		$this->boxes = array();

		// Permissions
		$this->rights = array();
		$this->rights_class = 'stock';

		$r = 0;

		$this->rights[$r][0] = 1001;
		$this->rights[$r][1] = 'Read stocks';
		$this->rights[$r][2] = 'r';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'lire';
		$this->rights[$r][5] = '';

		$r++;
		$this->rights[$r][0] = 1002;
		$this->rights[$r][1] = 'Create/Modify stocks';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'creer';
		$this->rights[$r][5] = '';

		$r++;
		$this->rights[$r][0] = 1003;
		$this->rights[$r][1] = 'Delete stock';
		$this->rights[$r][2] = 'd';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'supprimer';
		$this->rights[$r][5] = '';

		$r++;
		$this->rights[$r][0] = 1004;
		$this->rights[$r][1] = 'Read stock movements';
		$this->rights[$r][2] = 'r';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'mouvement';
		$this->rights[$r][5] = 'lire';

		$r++;
		$this->rights[$r][0] = 1005;
		$this->rights[$r][1] = 'Create/modify stock movements';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'mouvement';
		$this->rights[$r][5] = 'creer';

		$r++;
		$this->rights[$r][0] = 1011;
		$this->rights[$r][1] = 'inventoryReadPermission'; // Permission label
		$this->rights[$r][3] = 0; // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'inventory_advance'; // In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][5] = 'read'; // In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)

		$r++;
		$this->rights[$r][0] = 1012;
		$this->rights[$r][1] = 'inventoryCreatePermission'; // Permission label
		$this->rights[$r][3] = 0; // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'inventory_advance'; // In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][5] = 'write'; // In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)

		$r++;
		$this->rights[$r][0] = 1013;
		$this->rights[$r][1] = 'inventoryDeletePermission'; // Permission label
		$this->rights[$r][3] = 0; // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'inventory_advance'; // In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][5] = 'delete'; // In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)

		// TODO and if MAIN_FEATURES_LEVEL >= 2 after module is activated ?
		if ($conf->global->MAIN_FEATURES_LEVEL >= 2) {
			$r++;
			$this->rights[$r][0] = 1014;
			$this->rights[$r][1] = 'inventoryValidatePermission'; // Permission label
			$this->rights[$r][3] = 0; // Permission by default for new user (0/1)
			$this->rights[$r][4] = 'inventory_advance'; // In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
			$this->rights[$r][5] = 'validate'; // In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)

			$r++;
			$this->rights[$r][0] = 1015;
			$this->rights[$r][1] = 'inventoryChangePMPPermission'; // Permission label
			$this->rights[$r][3] = 0; // Permission by default for new user (0/1)
			$this->rights[$r][4] = 'inventory_advance'; // In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
			$this->rights[$r][5] = 'changePMP'; // In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		}

		// Main menu entries
		$this->menu = array(); // List of menus to add
		$r = 0;

		// Menus
		//-------
		$this->menu = 1; // This module add menu entries. They are coded into menu manager.


		// Exports
		//--------
		$r = 0;

		// Export warehouses
		$r++;
		$this->export_code[$r] = $this->rights_class.'_emplacement';
		$this->export_label[$r] = "Warehouses"; // Translation key (used only if key ExportDataset_xxx_z not found)
		$this->export_icon[$r] = "warehouse";
		$this->export_permission[$r] = array(array("stock", "lire"));
		$this->export_fields_array[$r] = array(
			'e.rowid'=>'IdWarehouse', 'e.ref'=>'LocationSummary', 'e.description'=>'DescWareHouse', 'e.lieu'=>'LieuWareHouse', 'e.address'=>'Address', 'e.zip'=>'Zip', 'e.town'=>'Town',
			'd.code_departement'=>'Departement', 'c.code'=>'CountryCode',
			'e.phone'=>'Phone', 'e.fax'=>'Fax', 'e.statut'=>'Status', 'pe.rowid'=>'ParentWarehouse', 'pe.ref'=>'LocationSummary'
		);
		$this->export_TypeFields_array[$r] = array(
			'e.ref'=>'Text', 'e.description'=>'Text', 'e.lieu'=>'Text', 'e.address'=>'Text', 'e.zip'=>'Text', 'e.town'=>'Text',
			'd.code_departement'=>'List:c_departements:code_departement:code_departement:', 'c.code'=>'List:c_country:code:code:',
			'e.phone'=>'Text', 'e.fax'=>'Text', 'e.statut'=>'Text', 'pe.rowid'=>'List:entrepot:ref:rowid:stock', 'pe.ref'=>'Text'
		);
		$this->export_entities_array[$r] = array();	// We define here only fields that use another icon that the one defined into export_icon
		$this->export_aggregate_array[$r] = array();	// TODO Not used yet
		$keyforselect = 'warehouse';
		$keyforelement = 'warehouse';
		$keyforaliasextra = 'extra';
		include DOL_DOCUMENT_ROOT.'/core/extrafieldsinexport.inc.php';

		$this->export_sql_start[$r] = 'SELECT DISTINCT ';
		$this->export_sql_end[$r]  = ' FROM '.MAIN_DB_PREFIX.'entrepot as e';
		$this->export_sql_end[$r] .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_departements as d ON d.rowid = e.fk_departement';
		$this->export_sql_end[$r] .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_country as c ON c.rowid = e.fk_pays';
		$this->export_sql_end[$r] .= ' LEFT JOIN '.MAIN_DB_PREFIX.'entrepot as pe ON pe.rowid = e.fk_parent';
		$this->export_sql_end[$r] .= ' LEFT JOIN '.MAIN_DB_PREFIX.'entrepot_extrafields as extra ON extra.fk_object = e.rowid';
		$this->export_sql_end[$r] .= ' WHERE e.entity IN ('.getEntity('stock').')';

		// Export stock (without batch number)
		$r++;
		$this->export_code[$r] = $this->rights_class.'_emplacement_product';
		$this->export_label[$r] = "Stocks"; // Translation key (used only if key ExportDataset_xxx_z not found)
		$this->export_icon[$r] = "warehouse";
		$this->export_permission[$r] = array(array("stock", "lire"));
		$this->export_fields_array[$r] = array(
			'e.rowid'=>'IdWarehouse', 'e.ref'=>'LocationSummary', 'e.description'=>'DescWareHouse', 'e.lieu'=>'LieuWareHouse', 'e.address'=>'Address', 'e.zip'=>'Zip', 'e.town'=>'Town',
			'p.rowid'=>"ProductId", 'p.ref'=>"Ref", 'p.fk_product_type'=>"Type", 'p.label'=>"Label", 'p.description'=>"Description", 'p.note'=>"Note",
			'p.price'=>"Price", 'p.tva_tx'=>'VAT', 'p.tosell'=>"OnSell", 'p.tobuy'=>'OnBuy', 'p.duration'=>"Duration",
			'p.datec'=>'DateCreation', 'p.tms'=>'DateModification', 'p.pmp'=>'PMPValue', 'p.cost_price'=>'CostPrice',
			'p.seuil_stock_alerte'=>'StockLimit',
		);
		$this->export_TypeFields_array[$r] = array(
			'e.rowid'=>'List:entrepot:ref::stock', 'e.ref'=>'Text', 'e.lieu'=>'Text', 'e.address'=>'Text', 'e.zip'=>'Text', 'e.town'=>'Text',
			'p.rowid'=>"Numeric", 'p.ref'=>"Text", 'p.fk_product_type'=>"Text", 'p.label'=>"Text", 'p.description'=>"Text", 'p.note'=>"Text",
			'p.price'=>"Numeric", 'p.tva_tx'=>'Numeric', 'p.tosell'=>"Boolean", 'p.tobuy'=>"Boolean", 'p.duration'=>"Duree",
			'p.datec'=>'Date', 'p.tms'=>'Date', 'p.pmp'=>'Numeric', 'p.cost_price'=>'Numeric',
			'ps.reel'=>'Numeric',
			'p.seuil_stock_alerte'=>'Numeric',
		);
		$this->export_entities_array[$r] = array(
			'p.rowid'=>"product", 'p.ref'=>"product", 'p.fk_product_type'=>"product", 'p.label'=>"product", 'p.description'=>"product", 'p.note'=>"product",
			'p.price'=>"product", 'p.tva_tx'=>'product', 'p.tosell'=>"product", 'p.tobuy'=>"product", 'p.duration'=>"product",
			'p.datec'=>'product', 'p.tms'=>'product', 'p.pmp'=>'product', 'p.cost_price'=>'product',
			'ps.reel'=>'stock',
			'p.seuil_stock_alerte'=>'product',
		);	// We define here only fields that use another icon that the one defined into export_icon
		$this->export_aggregate_array[$r] = array('ps.reel'=>'SUM'); // TODO Not used yet
		$this->export_dependencies_array[$r] = array('stock'=>array('p.rowid', 'e.rowid')); // We must keep this until the aggregate_array is used. To have a unique key, if we ask a field of a child, to avoid the DISTINCT to discard them.
		$keyforselect = 'product';
		$keyforelement = 'product';
		$keyforaliasextra = 'extra';
		include DOL_DOCUMENT_ROOT.'/core/extrafieldsinexport.inc.php';
		$this->export_fields_array[$r] = array_merge($this->export_fields_array[$r], array('ps.reel'=>'Stock'));

		$this->export_sql_start[$r] = 'SELECT DISTINCT ';
		$this->export_sql_end[$r]  = ' FROM '.MAIN_DB_PREFIX.'product as p LEFT JOIN '.MAIN_DB_PREFIX.'product_extrafields as extra ON extra.fk_object = p.rowid, '.MAIN_DB_PREFIX.'product_stock as ps, '.MAIN_DB_PREFIX.'entrepot as e';
		$this->export_sql_end[$r] .= ' WHERE p.rowid = ps.fk_product AND ps.fk_entrepot = e.rowid';
		$this->export_sql_end[$r] .= ' AND e.entity IN ('.getEntity('stock').')';

		// Export stock including batch number
		if (isModEnabled('productbatch')) {
			$langs->load("productbatch");

			// This request is same than previous but without field ps.stock (real stock in warehouse) and with link to subtable productbatch
			$r++;

			$this->export_code[$r] = $this->rights_class.'_emplacement_product_lot';
			$this->export_label[$r] = "StocksWithBatch"; // Translation key (used only if key ExportDataset_xxx_z not found)
			$this->export_icon[$r] = "warehouse";
			$this->export_permission[$r] = array(array("stock", "lire"));
			$this->export_fields_array[$r] = array(
				'e.rowid'=>'IdWarehouse', 'e.ref'=>'LocationSummary', 'e.description'=>'DescWareHouse', 'e.lieu'=>'LieuWareHouse', 'e.address'=>'Address', 'e.zip'=>'Zip', 'e.town'=>'Town',
				'p.rowid'=>"ProductId", 'p.ref'=>"Ref", 'p.fk_product_type'=>"Type", 'p.label'=>"Label", 'p.description'=>"Description", 'p.note'=>"Note",
				'p.price'=>"Price", 'p.tva_tx'=>'VAT', 'p.tosell'=>"OnSell", 'p.tobuy'=>'OnBuy', 'p.duration'=>"Duration",
				'p.datec'=>'DateCreation', 'p.tms'=>'DateModification', 'p.pmp'=>'PMPValue', 'p.cost_price'=>'CostPrice',
				'pb.rowid'=>'Id', 'pb.batch'=>'Batch', 'pb.qty'=>'Qty',
				'pl.eatby'=>'EatByDate', 'pl.sellby'=>'SellByDate'
			);
			$this->export_TypeFields_array[$r] = array(
				'e.rowid'=>'List:entrepot:ref::stock', 'e.ref'=>'Text', 'e.lieu'=>'Text', 'e.description'=>'Text', 'e.address'=>'Text', 'e.zip'=>'Text', 'e.town'=>'Text',
				'p.rowid'=>"Numeric", 'p.ref'=>"Text", 'p.fk_product_type'=>"Text", 'p.label'=>"Text", 'p.description'=>"Text", 'p.note'=>"Text",
				'p.price'=>"Numeric", 'p.tva_tx'=>'Numeric', 'p.tosell'=>"Boolean", 'p.tobuy'=>"Boolean", 'p.duration'=>"Duree",
				'p.datec'=>'DateCreation', 'p.tms'=>'DateModification', 'p.pmp'=>'PMPValue', 'p.cost_price'=>'CostPrice',
				'pb.batch'=>'Text', 'pb.qty'=>'Numeric',
				'pl.eatby'=>'Date', 'pl.sellby'=>'Date'
			);
			$this->export_entities_array[$r] = array(
				'p.rowid'=>"product", 'p.ref'=>"product", 'p.fk_product_type'=>"product", 'p.label'=>"product", 'p.description'=>"product", 'p.note'=>"product",
				'p.price'=>"product", 'p.tva_tx'=>'product', 'p.tosell'=>"product", 'p.tobuy'=>"product", 'p.duration'=>"product",
				'p.datec'=>'product', 'p.tms'=>'product', 'p.pmp'=>'product', 'p.cost_price'=>'product',
				'pb.rowid'=>'batch', 'pb.batch'=>'batch', 'pb.qty'=>'batch',
				'pl.eatby'=>'batch', 'pl.sellby'=>'batch'
			);	// We define here only fields that use another icon that the one defined into export_icon
			$this->export_aggregate_array[$r] = array('ps.reel'=>'SUM'); // TODO Not used yet
			$this->export_dependencies_array[$r] = array('stockbatch'=>array('pb.rowid'), 'batch'=>array('pb.rowid')); // We must keep this until the aggregate_array is used. To add unique key if we ask a field of a child to avoid the DISTINCT to discard them.
			$keyforselect = 'product_lot';
			$keyforelement = 'batch';
			$keyforaliasextra = 'extra';
			include DOL_DOCUMENT_ROOT.'/core/extrafieldsinexport.inc.php';

			$this->export_sql_start[$r] = 'SELECT DISTINCT ';
			$this->export_sql_end[$r]  = ' FROM '.MAIN_DB_PREFIX.'product_batch as pb';
			$this->export_sql_end[$r] .= ' INNER JOIN '.MAIN_DB_PREFIX.'product_stock as ps ON ps.rowid = pb.fk_product_stock';
			$this->export_sql_end[$r] .= ' INNER JOIN '.MAIN_DB_PREFIX.'product as p ON p.rowid = ps.fk_product';
			$this->export_sql_end[$r] .= ' LEFT JOIN '.MAIN_DB_PREFIX.'product_lot as pl ON pl.fk_product = p.rowid AND pl.batch = pb.batch';
			$this->export_sql_end[$r] .= ' LEFT JOIN '.MAIN_DB_PREFIX.'product_lot_extrafields as extra ON extra.fk_object = pl.rowid,';
			$this->export_sql_end[$r] .= ' '.MAIN_DB_PREFIX.'entrepot as e';
			$this->export_sql_end[$r] .= ' WHERE ps.fk_entrepot = e.rowid';
			$this->export_sql_end[$r] .= ' AND e.entity IN ('.getEntity('stock').')';
		}

		// Export of stock movements
		$r++;
		$this->export_code[$r] = $this->rights_class.'_movement';
		$this->export_label[$r] = "StockMovements"; // Translation key (used only if key ExportDataset_xxx_z not found)
		$this->export_icon[$r] = "movement";
		$this->export_permission[$r] = array(array("stock", "lire"));
		$this->export_fields_array[$r] = array(
			'sm.rowid'=>'MovementId', 'sm.value'=>'Qty', 'sm.datem'=>'DateMovement', 'sm.label'=>'MovementLabel', 'sm.inventorycode'=>'InventoryCode',
			'e.rowid'=>'IdWarehouse', 'e.ref'=>'LocationSummary', 'e.description'=>'DescWareHouse', 'e.lieu'=>'LieuWareHouse', 'e.address'=>'Address', 'e.zip'=>'Zip', 'e.town'=>'Town',
			'p.rowid'=>"ProductId", 'p.ref'=>"Ref", 'p.fk_product_type'=>"Type", 'p.label'=>"Label", 'p.description'=>"Description", 'p.note'=>"Note",
			'p.price'=>"Price", 'p.tva_tx'=>'VAT', 'p.tosell'=>"OnSell", 'p.tobuy'=>'OnBuy', 'p.duration'=>"Duration", 'p.datec'=>'DateCreation', 'p.tms'=>'DateModification'
		);
		$this->export_TypeFields_array[$r] = array(
			'sm.rowid'=>'Numeric', 'sm.value'=>'Numeric', 'sm.datem'=>'Date', 'sm.batch'=>'Text', 'sm.label'=>'Text', 'sm.inventorycode'=>'Text',
			'e.rowid'=>'List:entrepot:ref::stock', 'e.ref'=>'Text', 'e.description'=>'Text', 'e.lieu'=>'Text', 'e.address'=>'Text', 'e.zip'=>'Text', 'e.town'=>'Text',
			'p.rowid'=>"Numeric", 'p.ref'=>"Text", 'p.fk_product_type'=>"Text", 'p.label'=>"Text", 'p.description'=>"Text", 'p.note'=>"Text",
			'p.price'=>"Numeric", 'p.tva_tx'=>'Numeric', 'p.tosell'=>"Boolean", 'p.tobuy'=>"Boolean", 'p.duration'=>"Duree", 'p.datec'=>'Date', 'p.tms'=>'Date'
		);
		$this->export_entities_array[$r] = array(
			'e.rowid'=>'warehouse', 'e.ref'=>'warehouse', 'e.description'=>'warehouse', 'e.lieu'=>'warehouse', 'e.address'=>'warehouse', 'e.zip'=>'warehouse', 'e.town'=>'warehouse',
			'p.rowid'=>"product", 'p.ref'=>"product", 'p.fk_product_type'=>"product", 'p.label'=>"product", 'p.description'=>"product", 'p.note'=>"product",
			'p.price'=>"product", 'p.tva_tx'=>'product', 'p.tosell'=>"product", 'p.tobuy'=>"product", 'p.duration'=>"product", 'p.datec'=>'product', 'p.tms'=>'product'
		);	// We define here only fields that use another icon that the one defined into export_icon
		if (isModEnabled('productbatch')) {
			$this->export_fields_array[$r]['sm.batch'] = 'Batch';
			$this->export_TypeFields_array[$r]['sm.batch'] = 'Text';
			$this->export_entities_array[$r]['sm.batch'] = 'movement';
		}
		$this->export_aggregate_array[$r] = array('sm.value'=>'SUM'); // TODO Not used yet
		$this->export_dependencies_array[$r] = array('movement'=>array('sm.rowid')); // We must keep this until the aggregate_array is used. To add unique key if we ask a field of a child to avoid the DISTINCT to discard them.

		$this->export_sql_start[$r] = 'SELECT DISTINCT ';
		$this->export_sql_end[$r]  = ' FROM '.MAIN_DB_PREFIX.'product as p, '.MAIN_DB_PREFIX.'stock_mouvement as sm, '.MAIN_DB_PREFIX.'entrepot as e';
		$this->export_sql_end[$r] .= ' WHERE p.rowid = sm.fk_product AND sm.fk_entrepot = e.rowid';
		$this->export_sql_end[$r] .= ' AND e.entity IN ('.getEntity('stock').')';

		// Imports
		//--------

		$r = 0;

		// Import warehouses
		$r++;
		$this->import_code[$r] = $this->rights_class.'_'.$r;
		$this->import_label[$r] = "Warehouses"; // Translation key
		$this->import_icon[$r] = "warehouse";
		$this->import_entities_array[$r] = array(); // We define here only fields that use another icon that the one defined into import_icon
		$this->import_tables_array[$r] = array('e'=>MAIN_DB_PREFIX.'entrepot');
		$this->import_tables_creator_array[$r] = array('e'=>'fk_user_author');
		$this->import_fields_array[$r] = array('e.ref'=>"LocationSummary*",
				'e.description'=>"DescWareHouse",
				'e.lieu'=>"LieuWareHouse",
				'e.address'=>"Address",
				'e.zip'=>'Zip',
				'e.fk_departement'=>'StateCode',
				'e.fk_pays'=>'CountryCode',
				'e.phone'=>'Phone',
				'e.fax'=>'Fax',
				'e.statut'=>'Status',
				'e.fk_parent'=>'ParentWarehouse'
		);

		$this->import_convertvalue_array[$r] = array(
				'e.fk_departement'=>array('rule'=>'fetchidfromcodeid', 'classfile'=>'/core/class/cstate.class.php', 'class'=>'Cstate', 'method'=>'fetch', 'dict'=>'DictionaryStateCode'),
				'e.fk_pays'=>array('rule'=>'fetchidfromcodeid', 'classfile'=>'/core/class/ccountry.class.php', 'class'=>'Ccountry', 'method'=>'fetch', 'dict'=>'DictionaryCountry'),
				'e.fk_parent'=>array('rule'=>'fetchidfromref', 'classfile'=>'/product/stock/class/entrepot.class.php', 'class'=>'Entrepot', 'method'=>'fetch', 'element'=>'ref')
		);
		$this->import_regex_array[$r] = array('e.statut'=>'^[0|1]');
		$this->import_examplevalues_array[$r] = array('e.ref'=>"ALM001",
				'e.description'=>"Central Warehouse",
				'e.lieu'=>"Central",
				'e.address'=>"Route 66",
				'e.zip'=>'28080',
				'e.fk_departement'=>'matches field "code_departement" in table "'.MAIN_DB_PREFIX.'c_departements"',
				'e.fk_pays'=>'US/FR/DE etc. matches field "code" in table "'.MAIN_DB_PREFIX.'c_country"',
				'e.phone'=>'(+33)(0)123456789',
				'e.fax'=>'(+33)(0)123456790',
				'e.statut'=>'1',
				'e.fk_parent'=>'id or ref of warehouse'
		);
		$this->import_updatekeys_array[$r] = array('p.ref'=>'Ref');

		// Import stocks
		$r++;
		$this->import_code[$r] = $this->rights_class.'_'.$r;
		$this->import_label[$r] = "Stocks"; // Translation key
		$this->import_icon[$r] = "stock";
		$this->import_entities_array[$r] = array(); // We define here only fields that use another icon that the one defined into import_icon
		$this->import_tables_array[$r] = array('ps'=>MAIN_DB_PREFIX.'product_stock');
		$this->import_fields_array[$r] = array('ps.fk_product'=>"Product*", 'ps.fk_entrepot'=>"Warehouse*", 'ps.reel'=>"Stock*");

		$this->import_convertvalue_array[$r] = array(
			'ps.fk_product'=>array('rule'=>'fetchidfromref', 'classfile'=>'/product/class/product.class.php', 'class'=>'Product', 'method'=>'fetch', 'element'=>'product'),
			'ps.fk_entrepot'=>array('rule'=>'fetchidfromref', 'classfile'=>'/product/stock/class/entrepot.class.php', 'class'=>'Entrepot', 'method'=>'fetch', 'element'=>'ref')
		  );
		$this->import_examplevalues_array[$r] = array(
			'ps.fk_product'=>"id or ref of product", 'ps.fk_entrepot'=>"id or ref of warehouse", 'ps.reel'=>"10"
		);
		$this->import_updatekeys_array[$r] = array('ps.fk_product'=>'Product', 'ps.fk_entrepot'=>"Warehouse");
		$this->import_run_sql_after_array[$r] = array(    // Because we may change data that are denormalized, we must update dernormalized data after.
			'UPDATE '.MAIN_DB_PREFIX.'product as p SET stock = (SELECT SUM(ps.reel) FROM '.MAIN_DB_PREFIX.'product_stock ps WHERE ps.fk_product = p.rowid);'
		);
	}


	/**
	 *  Function called when module is enabled.
	 *  The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *  It also creates data directories
	 *
	 *  @param      string	$options    Options when enabling module ('', 'noboxes')
	 *  @return     int             	1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		global $conf, $langs;

		$result = $this->_load_tables('/install/mysql/', 'stock');
		if ($result < 0) {
			return -1; // Do not activate module if error 'not allowed' returned when loading module SQL queries (the _load_table run sql with run_sql with the error allowed parameter set to 'default')
		}

		// Permissions
		$this->remove($options);

		//ODT template
		$src = DOL_DOCUMENT_ROOT.'/install/doctemplates/stocks/template_warehouse.odt';
		$dirodt = DOL_DATA_ROOT.'/doctemplates/stocks';
		$dest = $dirodt.'/template_warehouse.odt';

		if (file_exists($src) && !file_exists($dest)) {
			require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
			dol_mkdir($dirodt);
			$result = dol_copy($src, $dest, 0, 0);
			if ($result < 0) {
				$langs->load("errors");
				$this->error = $langs->trans('ErrorFailToCopyFile', $src, $dest);
				return 0;
			}
		}

		$sql = array();

		$sql = array(
			 "DELETE FROM ".MAIN_DB_PREFIX."document_model WHERE nom = '".$this->db->escape($this->const[1][2])."' AND type = 'stock' AND entity = ".((int) $conf->entity),
			 "INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, type, entity) VALUES('".$this->db->escape($this->const[1][2])."','stock',".((int) $conf->entity).")",
			 "DELETE FROM ".MAIN_DB_PREFIX."document_model WHERE nom = '".$this->db->escape($this->const[2][2])."' AND type = 'mouvement' AND entity = ".((int) $conf->entity),
			 "INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, type, entity) VALUES('".$this->db->escape($this->const[2][2])."','mouvement',".((int) $conf->entity).")",
		);

		return $this->_init($sql, $options);
	}
}
