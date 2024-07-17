<?php
if (!defined("NOCSRFCHECK")) define('NOCSRFCHECK', 1);
if (!defined("NOTOKENRENEWAL")) define('NOTOKENRENEWAL', 1);

if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', 1); // Disables token renewal

require '../config.php';

$get = GETPOST('get');
$set = GETPOST('set');

switch ($get) {
	case 'batchLine':

		$qty = GETPOST('qty');
		$fk_product = GETPOST('fk_product');
		$lineid = GETPOST('lineid');
		$warehouse_id = GETPOST('warehouse_id');
		return _getBatchLines($qty, $fk_product, $lineid, $warehouse_id);

		break;

	default:
		break;
}

switch ($set) {

	default:
		break;
}

function _getBatchLines($quantityToBeDelivered, $fk_product, $lineid, $warehouse_id)
{
	global $conf, $db, $langs;

	dol_include_once('/product/class/product.class.php');
	$langs->load('sendings');
	$langs->load('productbatch');


	$product = new Product($db);
	$product->fetch($fk_product);
	$product->load_stock('warehouseopen');

	$staticwarehouse = new Entrepot($db);
	$staticwarehouse->fetch($warehouse_id);
	/*
	 * Copier coller de la card expedition
	 */

	if (isModEnabled('productbatch') && $product->hasbatch())
	{

		$out='';
		$subj = 0;
		// Define nb of lines suggested for this order line
		$nbofsuggested = 0;
		if (is_object($product->stock_warehouse[$warehouse_id]) && count($product->stock_warehouse[$warehouse_id]->detail_batch))
		{
			foreach ($product->stock_warehouse[$warehouse_id]->detail_batch as $dbatch)
			{
				$nbofsuggested++;
			}
		}
		//print '<input name="idl'.$lineid.'" type="hidden" value="'.$lineid.'">';
		if (is_object($product->stock_warehouse[$warehouse_id]) && count($product->stock_warehouse[$warehouse_id]->detail_batch))
		{
			foreach ($product->stock_warehouse[$warehouse_id]->detail_batch as $dbatch)
			{
				//var_dump($dbatch);
				$batchStock = + $dbatch->qty;  // To get a numeric
				$deliverableQty = min($quantityToBeDelivered, $batchStock);
				print '<!-- subj='.$subj.'/'.$nbofsuggested.' --><tr class="batch_'.$lineid.'">';
				print '<td colspan="9" ></td><td align="center">';
				print '<input name="qtyl'.$lineid.'_'.$subj.'" id="qtyl'.$lineid.'_'.$subj.'" type="text" size="4" value="'.$deliverableQty.'">';
				print '</td>';

				print '<!-- Show details of lot -->';
				print '<td align="left" colspan="3">';

				print $staticwarehouse->getNomUrl(0).' / ';

				print '<input name="batchl'.$lineid.'_'.$subj.'" type="hidden" value="'.$dbatch->id.'">';

				$detail = '';
				$detail .= $langs->trans("Batch").': '.$dbatch->batch;
				if (!empty($dbatch->sellby))
					$detail .= ' - '.$langs->trans("SellByDate").': '.dol_print_date($dbatch->sellby, "day");
				if (!empty($dbatch->eatby))
					$detail .= ' - '.$langs->trans("EatByDate").': '.dol_print_date($dbatch->eatby, "day");
				$detail .= ' - '.$langs->trans("Qty").': '.$dbatch->qty;
				$detail .= '<br>';
				print $detail;

				$quantityToBeDelivered -= $deliverableQty;
				if ($quantityToBeDelivered < 0)
				{
					$quantityToBeDelivered = 0;
				}
				$subj++;
				print '</td></tr>';
			}
		}
		else
		{
			print '<!-- Case there is no details of lot at all -->';
			print '<tr class="oddeven batch_'.$lineid.'"><td colspan="9"></td><td align="center">';
			print '<input name="qtyl'.$lineid.'_'.$subj.'" id="qtyl'.$lineid.'_'.$subj.'" type="text" size="4" value="0" disabled="disabled"> ';
			print '</td>';

			print '<td align="left"  colspan="3">';
			print img_warning().' '.$langs->trans("NoProductToShipFoundIntoStock", $staticwarehouse->libelle);
			print '</td></tr>';
		}
		return $out;
	}
	else
	{
		return '';
	}
}
