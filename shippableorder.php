<?php
/* Copyright (C) 2013 ATM Consulting <support@atm-consulting.fr>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
require 'config.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
dol_include_once('/expedition/class/expedition.class.php');
dol_include_once('/shippableorder/class/shippableorder.class.php');
dol_include_once('/product/class/html.formproduct.class.php');

$langs->load('orders');
$langs->load('deliveries');
$langs->load('companies');
$langs->load('bills');
$langs->load('stocks');
$langs->load('products');
$langs->load('other');
$langs->load('shippableorder@shippableorder');

$newToken = function_exists('newToken') ? newToken() : $_SESSION['newtoken'];

$orderyear = GETPOST("orderyear", "int");
$ordermonth = GETPOST("ordermonth", "int");
$deliveryyear = GETPOST("deliveryyear", "int");
$deliverymonth = GETPOST("deliverymonth", "int");
$sref = GETPOST('sref', 'alpha');
$sref_client = GETPOST('sref_client', 'alpha');
$snom = GETPOST('snom', 'alpha');
$sall = GETPOST('sall');
$socid = GETPOST('socid', 'int');
$search_user = GETPOST('search_user', 'int');
$search_sale = GETPOST('search_sale', 'int');
$search_status = GETPOST('search_status');
$search_status_cmd = GETPOST('search_status_cmd');
$sproduct = GETPOST('sproduct');
if (!is_array($search_status) && $search_status <= 0) {
	$search_status = array();
} else $search_status = (array)$search_status;

// Security check
$id = (GETPOST('orderid') ? GETPOST('orderid') : GETPOST('id', 'int'));
if (!empty($user->societe_id))
	$socid = $user->societe_id;
elseif (!empty($user->socid))
	$socid = $user->socid;
$result = restrictedArea($user, 'commande', $id, '');

$sortfield = GETPOST("sortfield", 'alpha');
$sortorder = GETPOST("sortorder", 'alpha');
$offset = 0;
$offset = 0;
$page = GETPOST("page", 'int');
$page = intval($page);
if ($page == - 1) {
	$page = 0;
}
if(!empty($page)){
	$offset = $conf->liste_limit * $page;
	$pageprev = $page - 1;
	$pagenext = $page + 1;
}
if (! $sortfield)
	$sortfield = 'c.date_livraison';
if (! $sortorder)
	$sortorder = 'ASC';

$limit = (GETPOST('show_all') == 1) ? false : $conf->liste_limit;

$diroutputpdf = $conf->shippableorder->multidir_output[$conf->entity];

// Initialize technical object to manage hooks of thirdparties. Note that conf->hooks_modules contains array array
$hookmanager->initHooks(array(
		'shippableorderlist'
));

/**
 * Actions
 */

$confirm = GETPOST('confirm');
$formconfirm = '';
$form=new Form($db);

$action = GETPOST('action','alphanohtml');

switch ($action) {
	case 'createShipping' :
		if (! empty($_REQUEST['subCreateShip'])) {
			$TIDCommandes = $_REQUEST['TIDCommandes'];
			$TEnt_comm = $_REQUEST['TEnt_comm'];

			$order = new ShippableOrder($db);
			$order->createShipping($TIDCommandes, $TEnt_comm);
		}

		if (! empty($_REQUEST['subSetSent'])) {
			$TIDCommandes = $_REQUEST['TIDCommandes'];
			$order = new Commande($db);
			foreach ( $TIDCommandes as $idCommande ) {
				$order->setStatut(2, $idCommande, 'commande');
			}
		}

		break;

	case 'remove_file' :
		$file = GETPOST('file');
		if (! empty($file)) {
			$file = DOL_DATA_ROOT . '/shippableorder/' . $file;

			$ret = dol_delete_file($file, 0, 0, 0);
			if ($ret) {
				setEventMessage($langs->trans("FileWasRemoved", GETPOST('file')));
			}
			else {
				setEventMessage($langs->trans("ErrorFailToDeleteFile", GETPOST('file')), 'errors');
			}
		}

		break;

		case 'delete_all_pdf_files':
			$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans('DeleteAllFiles'), $langs->trans('ConfirmDeleteAllFiles'), 'confirm_delete_all_pdf_files', '', 'no', 1);


			break;
		case 'confirm_delete_all_pdf_files':
			if($confirm == 'yes') {

				$order = new ShippableOrder($db);
				$order->removeAllPDFFile();

				setEventMessage($langs->trans("FilesWereRemoved"));
			}

			break;


		case 'archive_files':

		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans('ArchiveFiles'), $langs->trans('ConfirmArchiveFiles'), 'confirm_archive_files', '', 'no', 1);

		break;

	case 'confirm_archive_files':

		if($confirm == 'yes') {

			$order = new ShippableOrder($db);
			$order->zipFiles();

		}

		break;

	default :

		break;
}

/**
 * View
 */

$parameters = array(
		'socid' => $socid
);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hook

// Do we click on purge search criteria ?
if (GETPOST("button_removefilter_x")) {
	$search_user = '';
	$search_sale = '';
	$sref = '';
	$sref_client = '';
	$snom = '';
	$orderyear = '';
	$ordermonth = '';
	$deliverymonth = '';
	$deliveryyear = '';
	$search_status = array();
	$sproduct = '';
	$search_status_cmd = '';
}

/**
 * *********************************************************************************************************************
 * **************************************************View****************************************************************
 * ********************************************************************************************************************
 */

$now = dol_now();

$form = new Form($db);
$formother = new FormOther($db);
$formfile = new FormFile($db);
$companystatic = new Societe($db);

$help_url = "EN:Module_Customers_Orders|FR:Module_Commandes_Clients|ES:Módulo_Pedidos_de_clientes";
llxHeader('', $langs->trans("ShippableOrders"), $help_url);

echo $formconfirm;
?>
<script type="text/javascript">
$(document).ready(function() {

	// **This check determines if using a jQuery version 1.7 or newer which requires the use of the prop function instead of the attr function when not called on an attribute
	if ($().prop) {
		$("#checkall").click(function() {
			$(".checkforgen").prop('checked', true);
		});
		$("#checknone").click(function() {
			$(".checkforgen").prop('checked', false);
		});
	  }
	  else {
		$("#checkall").click(function() {
			$(".checkforgen").attr('checked', true);
		});
		$("#checknone").click(function() {
			$(".checkforgen").attr('checked', false);
		});
	  }


});
</script>
<?php

if(!empty($conf->global->SHIPPABLEORDER_SELECT_BY_LINE)){
	$sql = 'SELECT s.nom, s.rowid as socid, s.client, c.rowid, c.ref, cd.total_ht, c.ref_client, cd.rowid as lineid, cd.subprice, cd.fk_product,';
	$sql .= ' c.date_valid, c.date_commande, c.note_private, cde.date_de_livraison as date_livraison, c.fk_statut, c.facture as facturee,';

}
else
{
	$sql = 'SELECT s.nom, s.rowid as socid, s.client, c.rowid, c.ref, c.total_ht, c.ref_client,';
	$sql .= ' c.date_valid, c.date_commande, c.note_private, c.date_livraison, c.fk_statut, c.facture as facturee,';
}
if (!empty($conf->clinomadic->enabled)) {
	$sql .= " ce.reglement_recu,";
}
if(!empty($conf->global->SHIPPABLEORDER_SELECT_BY_LINE))  $sql .= 'cd.qty as qty_prod';
else $sql .= ' (SELECT SUM(qty) FROM ' . MAIN_DB_PREFIX . 'commandedet WHERE fk_commande = c.rowid AND fk_product > 0 AND product_type = 0) as qty_prod';
$sql .= ' FROM ' . MAIN_DB_PREFIX . 'societe as s';
$sql .= ', ' . MAIN_DB_PREFIX . 'commande as c';
$sql .= ', ' . MAIN_DB_PREFIX . 'commandedet as cd';
if (!empty($conf->clinomadic->enabled)) {
	$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "commande_extrafields as ce ON (ce.fk_object = cd.fk_commande)";
}
if(!empty($conf->global->SHIPPABLEORDER_SELECT_BY_LINE)){
	$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "commandedet_extrafields as cde ON (cde.fk_object = cd.rowid)";
	$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "product as prod ON (prod.rowid = cd.fk_product)";
}
// We'll need this table joined to the select in order to filter by sale
if ($search_sale > 0 || (! $user->rights->societe->client->voir && ! $socid))
	$sql .= ", " . MAIN_DB_PREFIX . "societe_commerciaux as sc";
if ($search_user > 0) {
	$sql .= ", " . MAIN_DB_PREFIX . "element_contact as ec";
	$sql .= ", " . MAIN_DB_PREFIX . "c_type_contact as tc";
}
$sql .= ' WHERE c.fk_soc = s.rowid';
$sql .= ' AND c.rowid = cd.fk_commande';
$sql .= ' AND c.entity = ' . $conf->entity;
if ($socid)
	$sql .= ' AND s.rowid = ' . $socid;
if (! $user->rights->societe->client->voir && ! $socid)
	$sql .= " AND s.rowid = sc.fk_soc AND sc.fk_user = " . $user->id;
if ($sref) {
	$sql .= natural_search('c.ref', $sref);
}
if ($sall) {
	$sql .= natural_search(array(
			'c.ref',
			'c.note_private'
	), $sall);
}
if ($ordermonth > 0) {
	if ($orderyear > 0 && empty($day))
		$sql .= " AND c.date_commande BETWEEN '" . $db->idate(dol_get_first_day($orderyear, $ordermonth, false)) . "' AND '" . $db->idate(dol_get_last_day($orderyear, $ordermonth, false)) . "'";
	else if ($orderyear > 0 && ! empty($day))
		$sql .= " AND c.date_commande BETWEEN '" . $db->idate(dol_mktime(0, 0, 0, $ordermonth, $day, $orderyear)) . "' AND '" . $db->idate(dol_mktime(23, 59, 59, $ordermonth, $day, $orderyear)) . "'";
	else
		$sql .= " AND date_format(c.date_commande, '%m') = '" . $ordermonth . "'";
} else if ($orderyear > 0) {
	$sql .= " AND c.date_commande BETWEEN '" . $db->idate(dol_get_first_day($orderyear, 1, false)) . "' AND '" . $db->idate(dol_get_last_day($orderyear, 12, false)) . "'";
}
if ($deliverymonth > 0)
{
	if ($deliveryyear > 0 && empty($day))
	{
		if(empty($conf->global->SHIPPABLEORDER_SELECT_BY_LINE))$sql .= " AND c.date_livraison BETWEEN '".$db->idate(dol_get_first_day($deliveryyear, $deliverymonth, false))."' AND '".$db->idate(dol_get_last_day($deliveryyear, $deliverymonth, false))."'";
		else $sql .= " AND cde.date_de_livraison BETWEEN '".$db->idate(dol_get_first_day($deliveryyear, $deliverymonth, false))."' AND '".$db->idate(dol_get_last_day($deliveryyear, $deliverymonth, false))."'";
	}
	else if ($deliveryyear > 0 && !empty($day))
	{
		if(empty($conf->global->SHIPPABLEORDER_SELECT_BY_LINE))$sql .= " AND c.date_livraison BETWEEN '".$db->idate(dol_mktime(0, 0, 0, $deliverymonth, $day, $deliveryyear))."' AND '".$db->idate(dol_mktime(23, 59, 59, $deliverymonth, $day, $deliveryyear))."'";
		else $sql .= " AND cde.date_de_livraison BETWEEN '".$db->idate(dol_mktime(0, 0, 0, $deliverymonth, $day, $deliveryyear))."' AND '".$db->idate(dol_mktime(23, 59, 59, $deliverymonth, $day, $deliveryyear))."'";

	}
	else
	{
		if(empty($conf->global->SHIPPABLEORDER_SELECT_BY_LINE))$sql .= " AND date_format(c.date_livraison, '%m') = '".$deliverymonth."'";
		else $sql .= " AND date_format(cde.date_de_livraison, '%m') = '".$deliverymonth."'";
	}
}
else if ($deliveryyear > 0)
{
	if(empty($conf->global->SHIPPABLEORDER_SELECT_BY_LINE))$sql .= " AND c.date_livraison BETWEEN '".$db->idate(dol_get_first_day($deliveryyear, 1, false))."' AND '".$db->idate(dol_get_last_day($deliveryyear, 12, false))."'";
	else $sql .= " AND cde.date_de_livraison BETWEEN '".$db->idate(dol_get_first_day($deliveryyear, 1, false))."' AND '".$db->idate(dol_get_last_day($deliveryyear, 12, false))."'";
}
if (! empty($snom)) {
	$sql .= natural_search('s.nom', $snom);
}
if (! empty($sproduct)) {
	$sql .= natural_search('prod.ref', $sproduct);
}
if (! empty($sref_client)) {
	$sql .= ' AND c.ref_client LIKE \'%' . $db->escape($sref_client) . '%\'';
}
if ($search_sale > 0)
	$sql .= " AND s.rowid = sc.fk_soc AND sc.fk_user = " . $search_sale;
if ($search_user > 0) {
	$sql .= " AND ec.fk_c_type_contact = tc.rowid AND tc.element='commande' AND tc.source='internal' AND ec.element_id = c.rowid AND ec.fk_socpeople = " . $search_user;
}

if($search_status_cmd > 0) $sql.= ' AND c.fk_statut = '.$search_status_cmd;
else $sql .= ' AND c.fk_statut IN (1,2)';

if (empty($conf->global->STOCK_SUPPORTS_SERVICES)) {
	$sql .= ' AND cd.product_type = 0';
}


if(!empty($conf->global->SHIPPABLEORDER_SELECT_BY_LINE))$sql.= ' GROUP BY cd.rowid, cde.date_de_livraison, s.rowid, c.rowid ';
else $sql .= ' GROUP BY c.rowid, s.rowid';
$sql.=  ' ORDER BY ' . $sortfield . ' ' . $sortorder;
if ($limit > 0) {
	$sql2 = $sql;
	$sql .= $db->plimit($limit + 1, $offset);
}

// echo $sql; exit;

// print $sql;
$resql = $db->query($sql);
if ($resql) {
	if ($socid) {
		$soc = new Societe($db);
		$soc->fetch($socid);
		$title = $langs->trans('ShippableOrders') . ' - ' . $soc->nom;
	} else {
		$title = $langs->trans('ShippableOrders');
	}

	$param = '';
	if ($socid)
		$param .= '&socid=' . $socid;
	if ($ordermonth)
		$param .= '&ordermonth=' . $ordermonth;
	if ($orderyear)
		$param .= '&orderyear=' . $orderyear;
	if ($deliverymonth)
		$param .= '&deliverymonth=' . $deliverymonth;
	if ($deliveryyear)
		$param .= '&deliveryyear=' . $deliveryyear;
	if ($sref)
		$param .= '&sref=' . $sref;
	if ($snom)
		$param .= '&snom=' . $snom;
	if ($sref_client)
		$param .= '&sref_client=' . $sref_client;
	if ($search_user > 0)
		$param .= '&search_user=' . $search_user;
	if ($search_sale > 0)
		$param .= '&search_sale=' . $search_sale;
	if (!empty($search_status)) {
		foreach($search_status as $status) $param .= '&search_status[]=' . $status;
	}
	if($search_status_cmd > 0)
		$param .= '&search_status_cmd=' . $search_status_cmd;
	if ($limit === false)
		$param .= '&show_all=1';

	$num = $db->num_rows($resql);
	$i = 0;

	if ($limit !== false) {
		$totalLine = null;
		if (isset($sql2)) {
			$resql2 = $db->query($sql2);
			if ($resql2)
				$totalLine = $db->num_rows($resql2);
		}
		print_barre_liste($title . '&nbsp;<a href="' . $_SERVER["PHP_SELF"] . '?show_all=1' . $param . '">' . $langs->trans('ShowAllLine') . '</a>', empty($page)?0:1, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $totalLine);
	} else {
		print_fiche_titre($title, '<a href="' . $_SERVER["PHP_SELF"] . '?show_all=0' . (str_replace('&show_all=1', '', $param)) . '">' . $langs->trans('NotShowAllLine') . '</a>', $picto = 'title_generic.png');
	}

	// Lignes des champs de filtre
	print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
	print '<input type="hidden" name="token" value="'.$newToken.'">';
	print '<table class="noborder liste" width="100%">';

	$moreforfilter = '';

	// If the user can view prospects other than his'
	if ($user->rights->societe->client->voir || $socid) {
		$langs->load("commercial");
		$moreforfilter .= $langs->trans('ThirdPartiesOfSaleRepresentative') . ': ';
		$moreforfilter .= $formother->select_salesrepresentatives($search_sale, 'search_sale', $user);
		$moreforfilter .= ' &nbsp; &nbsp; &nbsp; ';
	}
	// If the user can view prospects other than his'
	if ($user->rights->societe->client->voir || $socid) {
		$moreforfilter .= $langs->trans('LinkedToSpecificUsers') . ': ';
		$moreforfilter .= $form->select_dolusers($search_user, 'search_user', 1);
	}

	if(!empty($conf->global->SHIPPABLEORDER_SELECT_BY_LINE)){
		$moreforfilter .= '<td></td>';
	}
	if (! empty($moreforfilter)) {
		print '<tr class="liste_titre">';
		print '<td class="liste_titre" colspan="10">';
		print $moreforfilter;
		print '</td><td>';
		print '</td><td>';
		print '</td></tr>';
	}

	print '<tr class="liste_titre">';
	if ($limit === false)
		print '<input type="hidden" name="show_all" value="1" />';
	print_liste_field_titre($langs->trans('Ref'), $_SERVER["PHP_SELF"], 'c.ref', '', $param, '', $sortfield, $sortorder);
	if (!empty($conf->clinomadic->enabled))
		print_liste_field_titre($langs->trans('Règlement'), $_SERVER["PHP_SELF"], 'c.ref_client', '', $param, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('RefCustomerOrder'), $_SERVER["PHP_SELF"], 'c.ref_client', '', $param, '', $sortfield, $sortorder);
	if (!empty($conf->global->SHIPPABLEORDER_SELECT_BY_LINE))
		print_liste_field_titre($langs->trans('Product'), $_SERVER["PHP_SELF"], '', '', $param, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('Company'), $_SERVER["PHP_SELF"], 's.nom', '', $param, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('OrderDate'), $_SERVER["PHP_SELF"], 'c.date_commande', '', $param, 'align="right"', $sortfield, $sortorder);
	if (!empty($conf->global->SHIPPABLEORDER_SELECT_BY_LINE))
	{
		print_liste_field_titre($langs->trans('DeliveryDate'), $_SERVER["PHP_SELF"], 'cde.date_de_livraison', '', $param, 'align="right"', $sortfield, $sortorder);

		print_liste_field_titre($langs->trans('AmountHT'), $_SERVER["PHP_SELF"], 'cd.total_ht', '', $param, 'align="right"', $sortfield, $sortorder);
	}
	else
	{
		print_liste_field_titre($langs->trans('DeliveryDate'), $_SERVER["PHP_SELF"], 'c.date_livraison', '', $param, 'align="right"', $sortfield, $sortorder);

		print_liste_field_titre($langs->trans('AmountHT'), $_SERVER["PHP_SELF"], 'c.total_ht', '', $param, 'align="right"', $sortfield, $sortorder);
	}
	print_liste_field_titre($langs->trans('AmountHTToShip'), $_SERVER["PHP_SELF"], '', '', $param, 'align="right"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('Status'), $_SERVER["PHP_SELF"], 'c.fk_statut', '', $param, 'align="right"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('QtyProd'), $_SERVER["PHP_SELF"], 'qty_prod', '', $param, 'align="right"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('InStock'), $_SERVER["PHP_SELF"], 'qty_prod', '', $param, 'align="right"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('Warehouse'), $_SERVER["PHP_SELF"], 'qty_prod', '', $param, 'align="right"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('CreateShipment'), $_SERVER["PHP_SELF"], 'qty_prod', '', $param, 'align="right"', $sortfield, $sortorder);

	$generic_commande = new Commande($db);

	$formproduct = new FormProduct($db);
	$shippableOrder = new ShippableOrder($db);

	print '</tr>';
	print '<tr class="liste_titre">';
	print '<td class="liste_titre">';
	print '<input class="flat" size="6" type="text" name="sref" value="' . $sref . '">';
	print '</td>';
	print '<td class="liste_titre" align="left">';
	print '<input class="flat" type="text" size="6" name="sref_client" value="' . $sref_client . '">';
	print '</td>';
	if(!empty($conf->global->SHIPPABLEORDER_SELECT_BY_LINE)){
		print '<td class="liste_titre" align="left">';
		print '<input class="flat" type="text" size="6" name="sproduct" value="' . $sproduct . '">';
		print '</td>';
	}
	print '<td class="liste_titre" align="left">';
	print '<input class="flat" type="text" name="snom" value="' . $snom . '">';
	print '</td>';
	print '<td class="liste_titre">&nbsp;</td>';
	print '<td class="liste_titre" align="center">';
	//print $langs->trans('Month').': ';

	print '<input class="flat" type="text" size="1" maxlength="2" name="deliverymonth" value="'.$deliverymonth.'">';
	//print '&nbsp;'.$langs->trans('Year').': ';
	$formother->select_year($deliveryyear, 'deliveryyear', 1, 20, 5);
	print '</td>';
	print '<td class="liste_titre">&nbsp;</td>';
	print '<td class="liste_titre">&nbsp;</td>';
    print '<td class="liste_titre maxwidthonsmartphone" align="right">';
	$liststatus=array(
	    '1'=>$langs->trans("StatusOrderValidated"),
	    '2'=>$langs->trans("StatusOrderSentShort"),
	);
	print $form->selectarray('search_status_cmd', $liststatus, $search_status_cmd, 1);
    print '</td>';
	print '<td class="liste_titre">&nbsp;</td>';
	print '<td class="liste_titre" align="right">' . $shippableOrder->selectShippableOrderStatus('search_status', $search_status) . '</td>';
	// print '<td class="liste_titre">&nbsp;</td>';
	print '<td class="liste_titre" align="right">';
	print '<input type="image" class="liste_titre" name="button_search" src="' . img_picto($langs->trans("Search"), 'search.png', '', '', 1) . '" value="' . dol_escape_htmltag($langs->trans("Search")) . '" title="' . dol_escape_htmltag($langs->trans("Search")) . '">';
	print '&nbsp; ';
	print '<input type="image" class="liste_titre" name="button_removefilter" src="' . img_picto($langs->trans("Search"), 'searchclear.png', '', '', 1) . '" value="' . dol_escape_htmltag($langs->trans("RemoveFilter")) . '" title="' . dol_escape_htmltag($langs->trans("RemoveFilter")) . '">';
	print '</td>';
	print '<td class="liste_titre" align="center">';
	print '<a href="#" id="checkall">' . $langs->trans("All") . '</a> / <a href="#" id="checknone">' . $langs->trans("None") . '</a>';
	print '</td>';
	print '</tr>';
/*	print '</form>';
	print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
*/
	$var = true;
	$total = 0;
	$totaltoship = 0;
	$subtotal = 0;

	while ( $objp = $db->fetch_object($resql) ) {

		$BdisplayLine = true;

		$generic_commande->id = $objp->rowid;
		$generic_commande->ref = $objp->ref;
		$shippableOrder->isOrderShippable($objp->rowid);
		if(!empty($conf->global->SHIPPABLEORDER_SELECT_BY_LINE)){
			$orderLine = new OrderLine($db);
			$orderLine->fetch($objp->lineid);
		}

		if (! empty($search_status)) {

			$result = $shippableOrder->orderStockStatus(true, 'code', $objp->lineid);

			if(!in_array($result, $search_status)) {
				$BdisplayLine = false;
			}
		}
		if ($BdisplayLine == true) {

			$var = ! $var;
			print '<tr ' . $bc[$var] . '>';
			print '<td class="nowrap">';

			print '<table class="nobordernopadding"><tr class="nocellnopadd">';
			print '<td class="nobordernopadding nowrap">';
			print $generic_commande->getNomUrl(1);
			print '</td>';

			print '<td style="min-width: 20px" class="nobordernopadding nowrap">';
			if (($objp->fk_statut > 0) && ($objp->fk_statut < 3) && max($db->jdate($objp->date_commande), $db->jdate($objp->date_livraison)) < ($now - $conf->commande->client->warning_delay))
				print img_picto($langs->trans("Late"), "warning");
			if (! empty($objp->note_private)) {
				print ' <span class="note">';
				print '<a href="' . DOL_URL_ROOT . '/commande/note.php?id=' . $objp->rowid . '">' . img_picto($langs->trans("ViewPrivateNote"), 'object_generic') . '</a>';
				print '</span>';
			}
			print '</td>';

			print '<td width="16" align="right" class="nobordernopadding hideonsmartphone">';
			$filename = dol_sanitizeFileName($objp->ref);
			$filedir = $conf->commande->dir_output . '/' . dol_sanitizeFileName($objp->ref);
			$urlsource = $_SERVER['PHP_SELF'] . '?id=' . $objp->rowid;
			print $formfile->getDocumentsLink($generic_commande->element, $filename, $filedir);
			print '</td>';
			print '</tr></table>';

			print '</td>';

			// Payer : oui/non spécific Nomadic
			if (!empty($conf->clinomadic->enabled)) {
				print '<td align="center" class="nowrap" style="font-weight:bold;">' . ucfirst(($objp->reglement_recu != 'oui') ? "Non" : $objp->reglement_recu) . '</td>';
			}

			// Ref customer
			print '<td>' . $objp->ref_client . '</td>';
			if(!empty($conf->global->SHIPPABLEORDER_SELECT_BY_LINE)) {
				// fk product
				dol_include_once('/product/class/product.class.php');
				$prod = new Product($db);
				$prod->fetch($objp->fk_product);
				print '<td class="product" data-fk_prod="'.$objp->fk_product.'" >' . $prod->getNomUrl(1) . '</td>';
			}

			// Company
			$companystatic->id = $objp->socid;
			$companystatic->nom = $objp->nom;
			$companystatic->client = $objp->client;
			print '<td>';
			print $companystatic->getNomUrl(1, 'customer');
			print '</td>';

			// Order date
			$y = dol_print_date($db->jdate($objp->date_commande), '%Y');
			$m = dol_print_date($db->jdate($objp->date_commande), '%m');
			$ml = dol_print_date($db->jdate($objp->date_commande), '%B');
			$d = dol_print_date($db->jdate($objp->date_commande), '%d');
			print '<td align="right">';
			print $d . '/';
			print '<a href="' . $_SERVER['PHP_SELF'] . '?orderyear=' . $y . '&amp;ordermonth=' . $m . '">' . $m . '/</a>';
			print '<a href="' . $_SERVER['PHP_SELF'] . '?orderyear=' . $y . '">' . $y . '</a>';
			print '</td>';

			// Delivery date
			$y = dol_print_date($db->jdate($objp->date_livraison), '%Y');
			$m = dol_print_date($db->jdate($objp->date_livraison), '%m');
			$ml = dol_print_date($db->jdate($objp->date_livraison), '%B');
			$d = dol_print_date($db->jdate($objp->date_livraison), '%d');
			print '<td align="right">';
			print $d . '/';
			print '<a href="' . $_SERVER['PHP_SELF'] . '?deliveryyear=' . $y . '&amp;deliverymonth=' . $m . '">' . $m . '/</a>';
			print '<a href="' . $_SERVER['PHP_SELF'] . '?deliveryyear=' . $y . '">' . $y . '</a>';
			print '</td>';

			// Amount HT
			print '<td align="right" class="nowrap">' . price($objp->total_ht) . '</td>';
            if(empty($objp->lineid))$objp->lineid=0;
			if(empty($shippableOrder->TlinesShippable[$objp->lineid])) {
				$shippableOrder->TlinesShippable[$objp->lineid] = array(
					'stock' => 0,
					'shippable' => 0,
					'to_ship' => 0,
					'qty_shippable' => 0,
					'stock_virtuel' => 0,
					'isShippableVirtual' => 0,
					'qtyShippableVirtual' => 0
				);
			}
			// Amount HT remain to ship
			if(!empty($conf->global->SHIPPABLEORDER_SELECT_BY_LINE)){
				print '<td align="right" class="nowrap">' . price(round($shippableOrder->TlinesShippable[$objp->lineid]['to_ship']*$objp->subprice, 2)) . '</td>';
			}
			else print '<td align="right" class="nowrap">' . price(round($shippableOrder->order->total_ht_to_ship, 2)) . '</td>';

			// Statut
			print '<td align="right" class="nowrap">' . $generic_commande->LibStatut($objp->fk_statut, $objp->facturee, 5) . '</td>';

			// Quantité de produit
			print '<td align="right" class="qty" data-qty_shippable="'.$shippableOrder->TlinesShippable[$objp->lineid]['qty_shippable'].'" class="nowrap">' . $objp->qty_prod . '</td>';

			// Expédiable
            $TPictoShippable = $shippableOrder->orderLineStockStatus($orderLine,true);
			print  !empty($conf->global->SHIPPABLEORDER_SELECT_BY_LINE)?'<td align="right" class="nowrap">' .$TPictoShippable[0]. '</td>':'<td align="right" class="nowrap">' .$shippableOrder->orderStockStatus(true, 'txt', $objp->lineid) . '</td>';

			if (! empty($conf->global->SHIPPABLEORDER_DEFAULT_WAREHOUSE)) {
				$default_wharehouse = $conf->global->SHIPPABLEORDER_DEFAULT_WAREHOUSE;
			} else {
				// Sélection de l'entrepot à déstocker pour l'expédition
				// On met par défaut le premier entrepot créé
				$sql2 = "SELECT rowid";
				$sql2 .= " FROM " . MAIN_DB_PREFIX . "entrepot";
				$sql2 .= " ORDER BY rowid ASC";
				$sql2 .= " LIMIT 1";
				$resql2 = $db->query($sql2);
				$res2 = $db->fetch_object($resql2);
				$default_wharehouse = $res2->rowid;
			}

			if ((!empty($conf->global->SHIPPABLEORDER_SELECT_BY_LINE) && ($shippableOrder->TlinesShippable[$objp->lineid]['qty_shippable']) >0 && ($shippableOrder->TlinesShippable[$objp->lineid]['qty_shippable'] -  $shippableOrder->TlinesShippable[$objp->lineid]['to_ship'])==0)
				|| (empty($conf->global->SHIPPABLEORDER_SELECT_BY_LINE)&&$shippableOrder->nbShippable > 0)) {

				if(!empty($conf->global->SHIPPABLEORDER_SELECT_BY_LINE)) $checkId=$objp->lineid;
				else $checkId=$objp->rowid;
				// TEnt_comm[] : clef = id_commande val = id_entrepot
				/*echo strtotime($objp->date_livraison);exit;
				 echo dol_now();exit;*/

				// Checkbox pour créer expédition
				if(!empty($conf->global->SHIPPABLEORDER_SELECT_BY_LINE)){
					print '<td align="right" class="nowrap">' . $formproduct->selectWarehouses($default_wharehouse, 'TEnt_comm[' . $checkId . ']', '', 1,'',$objp->fk_product) . '</td>';
					$checked = $shippableOrder->is_ok_for_shipping($objp->lineid) && strtotime($objp->date_livraison) <= dol_now() ? 'checked="checked"' : '';
				}
				else {

					print '<td align="right" class="nowrap">' . $formproduct->selectWarehouses($default_wharehouse, 'TEnt_comm[' . $checkId . ']', '', 1) . '</td>';
					$checked = $shippableOrder->is_ok_for_shipping() && strtotime($objp->date_livraison) <= dol_now() ? 'checked="checked"' : '';
				}
				if (!empty($conf->global->SHIPPABLEORDER_NO_DEFAULT_CHECK)) {
					$checked = false;
				}

				print '<td align="right" class="nowrap">' . '<input class="checkforgen" type="checkbox" ' . $checked . ' name="TIDCommandes[]" value="' . $checkId . '" />' . '</td>';
			} else {

				print '<td colspan="2">&nbsp;</td>';
			}

			print '</tr>';

			$total += $objp->total_ht;
			if(!empty($conf->global->SHIPPABLEORDER_SELECT_BY_LINE))$totaltoship += $shippableOrder->TlinesShippable[$objp->lineid]['to_ship']*$objp->subprice;
			else $totaltoship += $shippableOrder->order->total_ht_to_ship;
			$subtotal += $objp->total_ht;
			$i ++;
		}
	}

	if ($total > 0) {
		print '<tr class="liste_total">';
		if ($limit === false) {
			print '<td align="left">' . $langs->trans("TotalHT") . '</td>';
		} else {
			print '<td align="left">' . $langs->trans("TotalHTforthispage") . '</td>';
		}
		if(!empty($conf->global->SHIPPABLEORDER_SELECT_BY_LINE)) print '<td colspan="6" align="right"">' . price($total) . '</td>';
		else print '<td colspan="5" align="right"">' . price($total) . '</td>';
		print '<td align="right"">' . price($totaltoship) . '<td colspan="5"></td>';
		print '</tr>';
	}

	print '</table>';



	if ($num > 0 && $user->rights->expedition->creer) {
		print '<input type="hidden" name="action" value="createShipping"/>';
        print '<br /><input style="float:right" class="butAction" type="submit" name="subCreateShip" value="' . $langs->trans('CreateShipmentButton') . '" />';
		if (!empty($conf->global->SHIPPABLEORDER_ALLOW_CHANGE_STATUS_WITHOUT_SHIPMENT) && empty($conf->global->SHIPPABLEORDER_SELECT_BY_LINE)) {
			print '<input style="float:right" class="butAction" type="submit" name="subSetSent" value="' . $langs->trans('SetOrderSentButton') . '" />';
		}
	}
	print '</form>';

	?>
<br>
<table>
	<tr>
		<td colspan="2">Légende :</td>
	</tr>
		<?php foreach($shippableOrder->statusShippable as $statusdesckey=>$statusdescval) {?>
		<tr>
		<td><?php echo $statusdescval['picto'];?></td>
		<td><?php echo $statusdescval['trans']; ?></td>
	</tr>
		<?php }?>
	</table>

<?php
	if (!empty($conf->global->SHIPPABLEORDER_GENERATE_GLOBAL_PDF)) {
		print '<br><br>';
		// We disable multilang because we concat already existing pdf.
		$formfile = new FormFile($db);
		$formfile->show_documents('shippableorder', '', $diroutputpdf, $urlsource, false, true, '', 1, 1, 0, 48, 1, $param, $langs->trans("GlobalGeneratedFiles"));

		echo '<div class="tabsAction">';
		echo '<a class="butAction" href="?action=archive_files">'.$langs->trans('ArchiveFiles').'</a>';
		echo '<a class="butAction" href="?action=delete_all_pdf_files">'.$langs->trans('DeleteAllFiles').'</a>';
		echo '</div>';
	}

	$db->free($resql);
} else {
	print dol_print_error($db);
}

if (!empty($conf->global->SHIPPABLEORDER_SELECT_BY_LINE))
{
	?>
	<script type="text/javascript">
		$(document).ready(function(){

			$("select[id^='TEnt_comm']").on('change', function() {

				let fk_product = $(this).closest('tr').find('td.product').data('fk_prod');
				let qty = $(this).closest('tr').find('td.qty').data('qty_shippable');
				let lineid = $(this).closest('tr').find('input.checkforgen').val();
				let tr = $(this).closest('tr');
				$.ajax({
					url: '<?php echo dol_buildpath('/shippableorder/script/interface.php', 1); ?>'
					,type: 'POST'
					,data: {
						get: 'batchLine'
						,qty: qty
						,fk_product: fk_product
						,lineid: lineid
						,warehouse_id: this.value
					}
					}).done(function(data) {
						$(".batch_"+lineid).remove();
						tr.after(data);
					});


			});

			$("select[id^='TEnt_comm']").each(function(){
				let fk_product = $(this).closest('tr').find('td.product').data('fk_prod');
				let qty = $(this).closest('tr').find('td.qty').html();
				let lineid = $(this).closest('tr').find('input.checkforgen').val();
				let tr = $(this).closest('tr');
				$.ajax({
					url: '<?php echo dol_buildpath('/shippableorder/script/interface.php', 1); ?>'
					,type: 'POST'
					,data: {
						get: 'batchLine'
						,qty: qty
						,fk_product: fk_product
						,lineid: lineid
						,warehouse_id: this.value
					}
					}).done(function(data) {
						$(".batch_"+lineid).remove();
						tr.after(data);
					});
			});

		});
	</script>
	<?php
}
llxFooter();

$db->close();
?>
