<?php
class ActionsShippableorder
{
     /** Overloading the doActions function : replacing the parent's function with the one below
      *  @param      parameters  meta datas of the hook (context, etc...)
      *  @param      object             the object you want to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
      *  @param      action             current action (if set). Generally create or edit or null
      *  @return       void
      */

    function formObjectOptions($parameters, &$object, &$action, $hookmanager)
    {
      	global $db, $langs;

		if (in_array('ordercard',explode(':',$parameters['context'])) && $object->statut < 3 && $object->id > 0)
        {
        	dol_include_once('/shippableorder/class/shippableorder.class.php');

			$shippableOrder = new ShippableOrder($db);
			$shippableOrder->isOrderShippable($object->id);
        	echo '<tr><td>'.$langs->trans('ShippableStatus').'</td>';
			echo '<td>'.$shippableOrder->orderStockStatus(false).'</td></tr>';
			$object->shippableorder = $shippableOrder;
        }

		return 0;
	}

	function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
    {
      	global $db, $langs, $conf;

		if (in_array('ordercard',explode(':',$parameters['context'])) && $object->statut < 3)
        {
            if (!empty($object->lines)) {
				dol_include_once('/shippableorder/class/shippableorder.class.php');
				include_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

				$shippableOrder =  &$object->shippableorder;
				$form = new Form($db);
				$virtualTooltip = ShippableOrder::prepareTooltip();
				$textColor = !empty($conf->global->THEME_ELDY_TEXTTITLE) ? $conf->global->THEME_ELDY_TEXTTITLE : '';


				$jsConf = array(
						'textColor' => $textColor,
						'langs' => array(
							'TheoreticalStock' => $form->textwithpicto($langs->trans('TheoreticalStock'), $virtualTooltip),
							'RealStock' => $langs->trans('RealStock')
						),
						'lines' => array()
				);

				foreach($object->lines as &$line) {
					$stock = $shippableOrder->orderLineStockStatus($line,true);
					$jsConf['lines'][] = array(
						'id' => $line->id,
						'stockReal' => $stock[0],
						'stockVirtual' => $stock[1]
					) ;
				}

                ?>
                <script type="text/javascript">

					let shipOrderJsConf = <?php echo json_encode($jsConf); ?>;
					let emptyCols = '<td class="linecolstockvirtual" align="right"></td><td class="linecolstock" align="right"></td>';
					let colSpanToAdd = 1; // TODO normalement c'est 2 mais je sais pas pourquoi ça créé un décalage

					// Header line
                    $('table#tablelines tr.liste_titre .linecoldescription').first().after(
						'<td class="linecolstock" align="right" style="color:' + shipOrderJsConf.textColor + ';">:' + shipOrderJsConf.langs.TheoreticalStock + '</td>'
						+ '<td class="linecolstock" align="right" style="' + shipOrderJsConf.textColor + ';">' + shipOrderJsConf.langs.RealStock + '</td>');



					// Subtotal Compatibility
					$('table#tablelines tr[rel="subtotal"]').each(function( index ) {
						let col = $(this).find('td[colspan]:first');
						if(col){
							// title and subtitle must have colspan if not it's pro
							let curentColSpan = parseInt(col.attr('colspan'));
							col.attr('colspan', curentColSpan + colSpanToAdd);
						}
					});

					if(shipOrderJsConf.lines != undefined && shipOrderJsConf.lines.length > 0){
						shipOrderJsConf.lines.forEach(function (item, index, arr){
							$('table#tablelines tr[id=row-' + item.id + '] td.linecoldescription').after(
								'<td class="linecolstockvirtual nowrap" align="right">'+ item.stockVirtual +'</td>'
								+'<td class="linecolstock nowrap" align="right">'+ item.stockReal +'</td>'
							);
						});
					}


                    $('table#tablelines tr.liste_titre_add td.linecoldescription').first().after(emptyCols);
                    $('table#tablelines tr.liste_titre_add').next().children('td.linecoldescription').first().after(emptyCols);
                    $('table#tablelines tr.liste_titre_create td.linecoldescription').first().after(emptyCols);
                    $('table#tablelines tr.liste_titre_create').next().children('td.linecoldescription').first().after(emptyCols);
                    $('#trlinefordates td:first').after(emptyCols); // Add empty column in objectline_create
                    if($('tr[id^="extrarow"]').length > 0) $('tr[id^="extrarow"] td:first').after(emptyCols);
                </script>
			<?php
            }

	}

}
