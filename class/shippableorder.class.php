<?php
class ShippableOrder
{
	function __construct (&$db) {

		global $langs;

		$langs->load('shippableorder@shippableorder');

		$this->TlinesShippable = array();
		$this->order = null;
		$this->nbProduct = 0;
		$this->nbShippable = 0;
		$this->nbPartiallyShippable = 0;

		$this->statusShippable =array(
				 1=>array(
						'trans'=>$langs->trans('LegendEnStock'),
				 		'transshort'=>$langs->trans('LegendEnStockShort'),
						'picto'=>img_picto('LegendEnStockShort', 'statut4.png'))
				,2=>array(
						'trans'=>$langs->trans('LegendStockPartiel'),
						'transshort'=>$langs->trans('LegendStockPartielShort'),
						'picto'=>img_picto('LegendStockPartielShort', 'statut1.png'))
				,3=>array(
						'trans'=>$langs->trans('LegendHorsStock'),
						'transshort'=>$langs->trans('LegendHorsStockShort'),
						'picto'=>img_picto('LegendHorsStockShort', 'statut8.png'))
				,4=>array(
						'trans'=>$langs->trans('LegendAlreadyShipped'),
						'transshort'=>$langs->trans('LegendAlreadyShippedShort'),
						'picto'=>img_picto('LegendAlreadyShippedShort', 'statut5.png'))
				);

		$this->db = & $db;

		$this->TProduct = array(); // Tableau des produits chargés pour éviter de recharger les même plusieurs fois
	}


	public function selectShippableOrderStatus($htmlname='search_status', $selected) {
		require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

		$form = new Form($this->db);

		foreach($this->statusShippable as $statusdesckey=>$statusdescval) {
			$arrayselect[$statusdesckey] = $statusdescval['transshort'];
		}

		if(method_exists($form, 'multiselectarray')) {
			return $form->multiselectarray($htmlname, $arrayselect,$selected, 1, 0, '', 0, '161');
		} else {
			return $form->selectarray($htmlname, $arrayselect,$selected[0], 1);
		}

	}

	function isOrderShippable($idOrder){
		global $conf;

		$db = &$this->db;

		$this->order = new Commande($db);
		$this->order->fetch($idOrder);
		$this->order->loadExpeditions();
		$this->order->fetchObjectLinked('','','','shipping');

		// Calcul du montant restant à expédier
		$this->order->total_ht_shipped = 0;
		if(!empty($this->order->linkedObjects['shipping'])) {
			foreach($this->order->linkedObjects['shipping'] as &$exp) {
				$this->order->total_ht_shipped += $exp->total_ht;
			}
		}
		$this->order->total_ht_to_ship = $this->order->total_ht - $this->order->total_ht_shipped;

		$this->nbShippable = 0;
		$this->nbPartiallyShippable = 0;
		$this->nbProduct = 0;

		$TSomme = array();
		foreach($this->order->lines as &$line){

			if (getDolGlobalString('SHIPPABLE_ORDER_ALLOW_ALL_LINE') || ($line->product_type==0 && $line->fk_product>0))
			{
				if(empty($line->nbShippable)) $line->nbShippable = 0;
				if(empty($line->nbPartiallyShippable)) $line->nbPartiallyShippable = 0;
				if(empty($line->nbProduct)) $line->nbProduct = 0;
				// Prise en compte des quantité déjà expédiéesz
				if(!getDolGlobalString('SHIPPABLEORDER_DONT_CHECK_DRAFT_SHIPPING_QTY') || !$this->isDraftShipping($line->id)) {

					if(!empty($this->order->expeditions[$line->id])) $qtyAlreadyShipped = $this->order->expeditions[$line->id];
					else $qtyAlreadyShipped = 0;

				}

				$line->qty_toship = $line->qty - $qtyAlreadyShipped;

				$isshippable = $this->isLineShippable($line, $TSomme);

				// Expédiable si toute la quantité est expédiable
				if(!empty($isshippable) && $isshippable == 1) {
					$line->nbShippable++;
					$this->nbShippable++;
				}

				if(!empty($isshippable) && $isshippable == 2) {
					$line->nbPartiallyShippable++;
					$this->nbPartiallyShippable++;
				}

				if($this->TlinesShippable[$line->id]['to_ship'] > 0) {
					$line->nbProduct++;
					$this->nbProduct++;
				}

			} elseif($line->product_type==1) { // On ne doit pas tenir compte du montant des services (et notament les frais de port) dans la colonne montant HT restant à expédier

					if (!getDolGlobalString('STOCK_SUPPORTS_SERVICES')){
						$this->order->total_ht_to_ship -= $line->total_ht;
					}
			}
		}
	}

	function isDraftShipping($fk_origin_line) {

		global $db;

		$sql = 'SELECT e.fk_statut
				FROM '.MAIN_DB_PREFIX.'expedition e
				INNER JOIN '.MAIN_DB_PREFIX.'expeditiondet ed ON (ed.fk_expedition = e.rowid)
				WHERE fk_origin_line = '.$fk_origin_line;

		$resql = $db->query($sql);
		if($resql) {
			$res = $db->fetch_object($resql);
			if(empty($res->fk_statut)) return true;
		}

		return false;

	}

	function isLineShippable(&$line, &$TSomme) {
		global $conf, $user;

		$db = &$this->db;
		if(empty($TSomme[$line->fk_product])) $TSomme[$line->fk_product] = 0;
		$TSomme[$line->fk_product] += $line->qty_toship;

		if(!isset($line->stock) && $line->fk_product > 0) {
			if(empty($this->TProduct[$line->fk_product])) {
				$produit = new Product($db);
				$produit->fetch($line->fk_product);
				$produit->load_stock(false);
				$this->TProduct[$line->fk_product] = $produit;
			} else {
				$produit = &$this->TProduct[$line->fk_product];
			}
			$line->stock = $produit->stock_reel;
			$line->stock_virtuel = $produit->stock_theorique;

			// Filtre par entrepot de l'utilisateur
			if(getDolGlobalString('SHIPPABLEORDER_ENTREPOT_BY_USER') && !empty($user->array_options['options_entrepot_preferentiel'])) {
				$line->stock = $produit->stock_warehouse[$user->array_options['options_entrepot_preferentiel']]->real;
			}
			//Filtrer stock uniquement des entrepôts en conf
			elseif(getDolGlobalString('SHIPPABLEORDER_SPECIFIC_WAREHOUSE')){
				$line->stock = 0;
				//Récupération des entrepôts valide
				$TIdWarehouse = explode(',',  getDolGlobalString('SHIPPABLEORDER_SPECIFIC_WAREHOUSE'));

				foreach($produit->stock_warehouse as $identrepot => $objecttemp ){
					if(in_array($identrepot, $TIdWarehouse)){
						$line->stock +=  $objecttemp->real;
					}
				}
			}
		}

        list($isShippable, $qtyShippable) = self::getQtyShippable($line->stock, $line, $TSomme);
        list($isShippableVirtual, $qtyShippableVirtual) = self::getQtyShippable($line->stock_virtuel, $line, $TSomme);
        $this->TlinesShippable[$line->id] = array(
            'stock' => price2num($line->stock, 'MS'),
            'shippable' => $isShippable,
            'to_ship' => $line->qty_toship,
            'qty_shippable' => $qtyShippable,
            'stock_virtuel' => price2num($line->stock_virtuel, 'MS'),
            'isShippableVirtual' => $isShippableVirtual,
            'qtyShippableVirtual' => $qtyShippableVirtual
        );

		return $isShippable;
	}

    /**
     * Get if line is shippable and qty shippable
     * @param float $stock
     * @param OrderLine $line
     * @param array $TSomme
     * @return array
     */
    public static function getQtyShippable($stock, $line, $TSomme) {
        global $conf;
        if (getDolGlobalString('SHIPPABLE_ORDER_ALLOW_SHIPPING_IF_NOT_ENOUGH_STOCK'))
		{
			$isShippable = 1;
			$qtyShippable = $line->qty;
		}
        else if($stock <= 0 || $line->qty_toship <= 0) {
			$isShippable = 0;
			$qtyShippable = 0;
		} else if ($TSomme[$line->fk_product] <= $stock) {
			$isShippable = 1;
			$qtyShippable = $line->qty_toship;
		} else {
			$isShippable = 2;
			$qtyShippable = $line->qty_toship - $TSomme[$line->fk_product] + $stock;
		}
        return array($isShippable, $qtyShippable);
    }

	/**
	 *
	 * @param string $short
	 * @param string $mode
	 * @return string|unknown
	 */
	function orderStockStatus($short = true, $mode = 'txt', $lineid=null) {
		global $langs, $conf;

		$txt = '';
		$obj = $this;
		if(getDolGlobalString('SHIPPABLEORDER_SELECT_BY_LINE') && $lineid>0){
			foreach($this->order->lines as $line){
				if($line->id == $lineid){
					$obj = $line;
					break;
				}
			}
		}

		if ($obj->nbProduct == 0)
		{
			$txt .= img_picto($langs->trans('TotallyShipped'), 'statut5.png');
			$code = 4;
		}
		else if ($obj->nbProduct == $obj->nbShippable)
		{
			$txt .= img_picto($langs->trans('EnStock'), 'statut4.png');
			$code = 1;
		}
		else if ($obj->nbPartiallyShippable > 0)
		{
			$txt .= img_picto($langs->trans('StockPartiel'), 'statut1.png');
			$code = 2;
		}
		else if ($obj->nbShippable == 0)
		{
			$txt .= img_picto($langs->trans('HorsStock'), 'statut8.png');
			$code = 3;
		}
		else
		{
			$txt .= img_picto($langs->trans('StockPartiel'), 'statut1.png');
			$code = 2;
		}





		$label = 'NbProductShippable';
		if ($short)
			$label = 'NbProductShippableShort';

		if(!getDolGlobalString('SHIPPABLEORDER_SELECT_BY_LINE'))$txt .= ' ' . $langs->trans($label, $this->nbShippable, $this->nbProduct);

		if ($mode == 'txt') {
			return $txt;
		} elseif ($mode == 'code') {
			return $code;
		} else {
			return $txt;
		}
	}

	function orderLineStockStatus(&$line, $withStockVisu = false){
		global $langs;
		if(empty($line)) $line = new stdClass();
		if(empty($line->id)) $line->id = 0;
		if(isset($this->TlinesShippable[$line->id])) {
			$isShippable = $this->TlinesShippable[$line->id];
		} else {
			return '';
		}

        $picto = self::getPicto($isShippable['to_ship'], $isShippable['shippable'], $isShippable['stock'], $isShippable['qty_shippable'], $line, 'stock');
        $virtualPicto = self::getPicto($isShippable['to_ship'], $isShippable['isShippableVirtual'], $isShippable['stock_virtuel'], $isShippable['qtyShippableVirtual'], $line, 'virtualStock');

		if($withStockVisu) {
			return array($isShippable['stock'].' '.$picto, $isShippable['stock_virtuel'].' '.$virtualPicto);
		}
		else{
			return array($picto, $virtualPicto);
		}


	}

    /**
     * Prepare virtual tooltip msg
     * @return string
     */
    public static function prepareTooltip() {
        global $langs, $conf;
        $out = $langs->trans('VirtualStockDetailHeader');

		if (isModEnabled('mrp')) {
			$out .= $langs->trans('VirtualStockDetail', $langs->trans('MO'), $langs->trans('MO_status'));
		}

		// Stock decrease mode
		if (getDolGlobalString('STOCK_CALCULATE_ON_SHIPMENT') || getDolGlobalString('STOCK_CALCULATE_ON_SHIPMENT_CLOSE') || getDolGlobalString('STOCK_CALCULATE_ON_BILL')) {
            $out .= $langs->trans('VirtualStockDetail', $langs->trans('Orders'), $langs->trans('Orders_status'));
			if (getDolGlobalString('STOCK_CALCULATE_ON_SHIPMENT')) {
				$out .= $langs->trans('VirtualStockDetail', $langs->trans('ShippableOrder_Shipments'), $langs->trans('Shipments_status'));
			} elseif (getDolGlobalString('STOCK_CALCULATE_ON_SHIPMENT_CLOSE')) {
				$out .= $langs->trans('VirtualStockDetail', $langs->trans('ShippableOrder_Shipments'), $langs->trans('Shipments_status_closed'));
			}

		}
		// Stock Increase mode
		if (getDolGlobalString('STOCK_CALCULATE_ON_RECEPTION')
            || getDolGlobalString('STOCK_CALCULATE_ON_RECEPTION_CLOSE')
            || getDolGlobalString('STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER')
            || getDolGlobalString('STOCK_CALCULATE_ON_SUPPLIER_VALIDATE_ORDER')
            || getDolGlobalString('STOCK_CALCULATE_ON_SUPPLIER_BILL')) {
            $statusCmdFourn = 'PO_status';
            if (isset($includedraftpoforvirtual)) {
                $statusCmdFourn .= '_all';
            }
            if(!getDolGlobalString('STOCK_CALCULATE_ON_SUPPLIER_VALIDATE_ORDER')) $out .= $langs->trans('VirtualStockDetail', $langs->trans('PO'), $langs->trans($statusCmdFourn));
            $out .= $langs->trans('VirtualStockDetail', $langs->trans('ShippableOrder_Receptions'), $langs->trans('Receptions_status'));

		}
        return $out;
    }

    /**
     * Get picto shippable order line
     * @param string $type
	 * @param float $toship
     * @param float $shippable
     * @param float $stock
     * @param float $qty_shippable
     * @param OrderLine $line
     * @return string
     */
    public static function getPicto($toship, $shippable, $stock, $qty_shippable, $line, $type = 'stock') {
        $pictopath = self::getPictoPath($toship, $shippable);
		$infos = self::getPictoInfos($stock, $toship, $qty_shippable, $type);
        $picto = '<img src="'.$pictopath.'" border="0" title="'.$infos.'">';
		if($toship > 0 && $toship != $line->qty) {
			$picto.= ' ('.$toship.')';
		}
        return $picto;
    }

    /**
     * Get picto qty infos
     * @param string $type
	 * @param float $stock
     * @param float $toship
     * @param float $qty_shippable
     * @return string
     */
    public static function getPictoInfos($stock, $toship, $qty_shippable, $type = 'stock') {
        global $langs;
        if($type == 'stock') {
			$infos = $langs->trans('QtyInStock', $stock);
			$infos .= " - ".$langs->trans('RemainToShip', $toship);
			$infos .= " - ".$langs->trans('QtyShippable', $qty_shippable);
			return $infos;
		} else if($type == 'virtualStock') {
			$infos = $langs->trans('VirtualQtyInStock', $stock);
			$infos .= " - ".$langs->trans('RemainToShip', $toship);
			return $infos;
		}
    }

    /**
     * Get picto img status
     * @param int $to_ship
     * @param int $shippable
     * @return string
     */
    public static function getPictoPath($to_ship, $shippable) {
        // Produit déjà totalement expédié
		if($to_ship <= 0) {
			$pictopath = img_picto('', 'statut5.png', '', false, 1);
		}

		// Produit avec un reste à expédier
		else if($shippable == 1) {
			$pictopath = img_picto('', 'statut4.png', '', false, 1);
		} elseif($shippable == 0) {
			$pictopath = img_picto('', 'statut8.png', '', false, 1);
		} else {
			$pictopath = img_picto('', 'statut1.png', '', false, 1);
		}
        return $pictopath;
    }

	function is_ok_for_shipping($lineid=''){
		global $conf;
		$obj=$this;
		if(getDolGlobalString('SHIPPABLEORDER_SELECT_BY_LINE') && $lineid>0){
			foreach($this->order->lines as $line){
				if($line->id == $lineid){
					$obj = $line;
					break;
				}
			}

		}

		if($obj->nbProduct == $obj->nbShippable && $obj->nbShippable != 0) return true;

		return false;
	}

	function orderCommandeByClient($TIDCommandes) {

		$db = &$this->db;

		$TCommande = array();
		//var_dump($TIDCommandes);
		foreach($TIDCommandes as $id_commande) {
			$o=new Commande($db);
			$o->fetch($id_commande);

			if($o->statut != 3) $TCommande[] = $o;

		}

		usort($TCommande, array('ShippableOrder','_sort_by_client'));

		$TIDCommandes=array();
		foreach($TCommande as &$o ) {

			$TIDCommandes[] = $o->id;
		}

		//var_dump($TIDCommandes);
		return $TIDCommandes;
	}
	function _sort_by_client($a, $b) {

		if($a->socid < $b->socid) return -1;
		else if($a->socid > $b->socid) return 1;
		else return 0;

	}

	function removeAllPDFFile() {
		global $conf, $langs;
		$dir = $conf->shippableorder->multidir_output[$conf->entity].'/';

		$TFile = dol_dir_list( $dir );

		$inputfile = array();
		foreach($TFile as $file) {

			$ext = strtolower( pathinfo($file['fullname'], PATHINFO_EXTENSION) );
			if($ext == 'pdf') {
				$ret = dol_delete_file($file['fullname'], 0, 0, 0);
			}
		}


	}
	function zipFiles() {
		global $conf, $langs;

		if (defined('ODTPHP_PATHTOPCLZIP'))
		{

			include_once ODTPHP_PATHTOPCLZIP.'/pclzip.lib.php';

			$dir = $conf->shippableorder->multidir_output[$conf->entity].'/';

			$file = 'archive_'.date('Ymdhis').'.zip';

			if(file_exists($file))	unlink($file);

				$archive = new PclZip($dir.$file);

				$TFile = dol_dir_list( $dir );

				$inputfile = array();
				foreach($TFile as $file) {

					$ext =strtolower(  pathinfo($file['fullname'], PATHINFO_EXTENSION) );
					if($ext == 'pdf') {
						$inputfile[] = $file['fullname'];
					}
				}
				if(count($inputfile)==0){
					setEventMessage($langs->trans('NoFileInDirectory'),'warnings');
					return;
				}


				$archive->add($inputfile, PCLZIP_OPT_REMOVE_PATH, $dir);

				setEventMessage($langs->trans('FilesArchived'));

				$this->removeAllPDFFile();
		}
		else {

			print "ERREUR : Librairie Zip non trouvée";
		}

	}

	/**
	 * Création automatique des expéditions à partir de la liste des expédiables, uniquement avec les quantité expédiables
	 */
	function createShipping($TIDCommandes, $TEnt_comm) {
		global $user, $langs, $conf , $hookmanager;
		$db = &$this->db;
		dol_include_once('/expedition/class/expedition.class.php');
		dol_include_once('/core/modules/expedition/modules_expedition.php');
		dol_include_once('/core/lib/product.lib.php');

        // Option pour la génération PDF
		$hidedetails = (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS') ? 1 : 0);
		$hidedesc = (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_HIDE_DESC') ? 1 : 0);
		$hideref = (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_HIDE_REF') ? 1 : 0);

		$nbShippingCreated = 0;

		if (isset($TIDCommandes) && is_array($TIDCommandes) && count($TIDCommandes) > 0)
		{
			if (getDolGlobalString('SHIPPABLEORDER_SELECT_BY_LINE'))
			{
				$TToShip = $this->groupLineByOrder($TIDCommandes); //On fait une expédition par commande

				foreach ($TToShip as $id_commande => $lineids)
				{

					$this->isOrderShippable($id_commande);

					$shipping = new Expedition($db);
					$shipping->origin = 'commande';
					$shipping->origin_id = $id_commande;
					$shipping->date_delivery = $this->order->delivery_date;
					$shipping->note_public = $this->order->note_public;
					$shipping->note_private = $this->order->note_private;
					$shipping->shipping_method_id = $this->order->shipping_method_id;
					$shipping->ref_customer = $this->order->ref_client;

					$shipping->weight_units = 0;
					$shipping->weight = "NULL";
					$shipping->sizeW = "NULL";
					$shipping->sizeH = "NULL";
					$shipping->sizeS = "NULL";
					$shipping->size_units = 0;
					$shipping->socid = $this->order->socid;
					$shipping->modelpdf = getDolGlobalString('SHIPPABLEORDER_GENERATE_SHIPMENT_PDF') ? getDolGlobalString('SHIPPABLEORDER_GENERATE_SHIPMENT_PDF')  : 'rouget';

					foreach ($this->order->lines as $line)
					{


                        $parameters = array('line' => $line ,'TEnt_comm'=>$TEnt_comm,'shipping'=> &$shipping);
                        $reshook = $hookmanager->executeHooks('handleExpeditionTitleAndTotal',$parameters , $this, $action);    // Note that $action and $object may have been modified by some hooks
                        if ($reshook < 0){
                            setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
                        }
						if ($this->TlinesShippable[$line->id]['stock'] > 0 && in_array($line->id, $lineids))
						{
                            if (isModEnabled('productbatch') && ! empty($line->fk_product) && ! empty($line->product_tobatch)){
								dol_include_once('/product/class/product.class.php');
								$product = new Product($db);
								$product->fetch($line->fk_product);
								$product->load_stock('warehouseopen');
								$TBatch = $this->generateTBatch($line->id);
								$shipping->addline_batch($TBatch, $line->array_options);

							}else {
								$shipping->addline($TEnt_comm[$line->id], $line->id, (($this->TlinesShippable[$line->id]['qty_shippable'] > $this->TlinesShippable[$line->id]['to_ship']) ? $this->TlinesShippable[$line->id]['to_ship'] : $this->TlinesShippable[$line->id]['qty_shippable']), $line->array_options);
							}


						}
					}

					$nbShippingCreated++;
					$shipping->create($user);

					// Valider l'expédition
					if (getDolGlobalString('SHIPPABLE_ORDER_AUTO_VALIDATE_SHIPPING'))
					{
						if (empty($shipping->ref))
							$shipping->ref = '(PROV'.$shipping->id.')';
						$shipping->statut = 0;
						$shipping->valid($user);
					}

					// Génération du PDF
					if (getDolGlobalString('SHIPPABLEORDER_GENERATE_SHIPMENT_PDF'))
						$TFiles[] = $this->shipment_generate_pdf($shipping, $hidedetails, $hidedesc, $hideref);
				}
			} else {

				$TIDCommandes = $this->orderCommandeByClient($TIDCommandes);

				foreach ($TIDCommandes as $id_commande)
				{

					$this->isOrderShippable($id_commande);

					$shipping = new Expedition($db);
					$shipping->origin = 'commande';
					$shipping->origin_id = $id_commande;
					$shipping->date_delivery = $this->order->delivery_date;
					$shipping->note_public = $this->order->note_public;
					$shipping->note_private = $this->order->note_private;
					$shipping->ref_customer = $this->order->ref_client;

					$shipping->weight_units = 0;
					$shipping->weight = "NULL";
					$shipping->sizeW = "NULL";
					$shipping->sizeH = "NULL";
					$shipping->sizeS = "NULL";
					$shipping->size_units = 0;
					$shipping->socid = $this->order->socid;
					$shipping->modelpdf = getDolGlobalString('SHIPPABLEORDER_GENERATE_SHIPMENT_PDF') ? getDolGlobalString('SHIPPABLEORDER_GENERATE_SHIPMENT_PDF')  : 'rouget';

					foreach ($this->order->lines as $line)
					{


					    $parameters = array('line' => $line ,'TEnt_comm'=>$TEnt_comm,'shipping'=> &$shipping);
					    $reshook = $hookmanager->executeHooks('handleExpeditionTitleAndTotal',$parameters , $this, $action);    // Note that $action and $object may have been modified by some hooks
                        if ($reshook < 0){
                            setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
                        }

					    if (!empty($this->TlinesShippable[$line->id]) && $this->TlinesShippable[$line->id]['stock'] > 0)
						{
                            $r  = ($this->TlinesShippable[$line->id]['qty_shippable'] > $this->TlinesShippable[$line->id]['to_ship']) ? $this->TlinesShippable[$line->id]['to_ship'] : $this->TlinesShippable[$line->id]['qty_shippable'];
							$shipping->addline($TEnt_comm[$this->order->id], $line->id, $r, $line->array_options);
						}
					}

					$nbShippingCreated++;
					$shipping->create($user);

					// Valider l'expédition
					if (getDolGlobalString('SHIPPABLE_ORDER_AUTO_VALIDATE_SHIPPING'))
					{
						if (empty($shipping->ref))
							$shipping->ref = '(PROV'.$shipping->id.')';
						$shipping->statut = 0;
						$shipping->valid($user);
					}

					// Génération du PDF
					if (getDolGlobalString('SHIPPABLEORDER_GENERATE_SHIPMENT_PDF'))
						$TFiles[] = $this->shipment_generate_pdf($shipping, $hidedetails, $hidedesc, $hideref);
				}
			}

			$TURL = array();
			foreach($_REQUEST as $k=>$v) {
				if($k!='TIDCommandes' && $k!='TEnt_comm' && $k!='action' && $k!='subCreateShip') $TURL[$k] = $v;
			}


			if($nbShippingCreated > 0) {
				if(getDolGlobalString('SHIPPABLEORDER_GENERATE_GLOBAL_PDF')) $this->generate_global_pdf($TFiles);

				setEventMessage($langs->trans('NbShippingCreated', $nbShippingCreated));
				$dol_version = (float) DOL_VERSION;

				if (getDolGlobalString('SHIPPABLE_ORDER_DISABLE_AUTO_REDIRECT'))
				{

					header("Location: ".$_SERVER["PHP_SELF"].'?'.http_build_query($TURL) );
				}else{
					if ($dol_version <= 3.6) header("Location: ".dol_buildpath('/expedition/liste.php',1));
					else header("Location: ".dol_buildpath('/expedition/list.php',1));
					exit;
				}
			}
			else{
				setEventMessage($langs->trans('NoOrderSelectedOrAlreadySent'), 'warnings');
				$dol_version = (float) DOL_VERSION;

				if (getDolGlobalString('SHIPPABLE_ORDER_DISABLE_AUTO_REDIRECT'))
				{
					header("Location: ".$_SERVER["PHP_SELF"].'?'.http_build_query($TURL) );
				}else{
					if ($dol_version <= 3.6) header("Location: ".dol_buildpath('/expedition/liste.php',1));
					else header("Location: ".dol_buildpath('/expedition/list.php',1));
					exit;
				}
			}
		} else {
			setEventMessage($langs->trans('NoOrderSelectedOrAlreadySent'), 'warnings');
			$dol_version = (float) DOL_VERSION;

			if (getDolGlobalString('SHIPPABLE_ORDER_DISABLE_AUTO_REDIRECT'))
			{
				header("Location: ".$_SERVER["PHP_SELF"]);
			}else{
				if ($dol_version <= 3.6) header("Location: ".dol_buildpath('/expedition/liste.php',1));
				else header("Location: ".dol_buildpath('/expedition/list.php',1));
				exit;
			}
		}
	}

	function groupLineByOrder($TIDCommandeDet = array()){
		global $db;
		$TOrderLine = array();
		if(!empty($TIDCommandeDet)){
			foreach($TIDCommandeDet as $orderline_id){
				$sql = "SELECT fk_commande FROM ".MAIN_DB_PREFIX."commandedet WHERE rowid=".$orderline_id;
				$resql = $db->query($sql);
				if(!empty($resql)){
					$obj = $db->fetch_object($resql);
					if(!empty($TOrderLine[$obj->fk_commande]))$TOrderLine[$obj->fk_commande][]=$orderline_id;
					else $TOrderLine[$obj->fk_commande] = array($orderline_id);
				}
			}
		}
		return $TOrderLine;
	}

	function shipment_generate_pdf(&$shipment, $hidedetails, $hidedesc, $hideref) {
		global $conf, $langs;

		$db = &$this->db;
		// Il faut recharger les lignes qui viennent juste d'être créées
		$shipment->fetch($shipment->id);
		/*echo '<pre>';
		print_r($shipment);
		exit;*/

		$outputlangs = $langs;
		if (getDolGlobalString('MAIN_MULTILANGS')) {$newlang=$shipment->client->default_lang;}
		if (! empty($newlang)) {
			$outputlangs = new Translate("",$conf);
			$outputlangs->setDefaultLang($newlang);
		}
		if((float)DOL_VERSION > 5) $result=$shipment->generateDocument($shipment->modelpdf, $outputlangs,$hidedetails, $hidedesc, $hideref);
		else $result=expedition_pdf_create($db, $shipment, $shipment->modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref);

		if($result > 0) {
			$objectref = dol_sanitizeFileName($shipment->ref);
			$dir = $conf->expedition->dir_output . "/sending/" . $objectref;
			$file = $dir . "/" . $objectref . ".pdf";
			return $file;
		}

		return '';
	}

	function generate_global_pdf($TFiles) {
		global $langs, $conf;

        // Create empty PDF
        $pdf=pdf_getInstance();
        if (class_exists('TCPDF'))
        {
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
        }
        $pdf->SetFont(pdf_getPDFFont($langs));

        if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) $pdf->SetCompression(false);

		// Add all others
		foreach($TFiles as $file)
		{
			// Charge un document PDF depuis un fichier.
			$pagecount = $pdf->setSourceFile($file);
			for ($i = 1; $i <= $pagecount; $i++)
			{
				$tplidx = $pdf->importPage($i);
				$s = $pdf->getTemplatesize($tplidx);
				$pdf->AddPage($s['h'] > $s['w'] ? 'P' : 'L');
				$pdf->useTemplate($tplidx);
			}
		}

		// Create output dir if not exists
		$diroutputpdf = $conf->shippableorder->multidir_output[$conf->entity];
		dol_mkdir($diroutputpdf);

		// Save merged file
		$filename=strtolower(dol_string_nospecial(dol_sanitizeFileName($langs->transnoentities("OrderShipped"))));

		if ($pagecount)
		{
			$now=dol_now();
			$file=$diroutputpdf.'/'.$filename.'_'.dol_print_date($now,'dayhourlog').'.pdf';
			$pdf->Output($file,'F');
			if (getDolGlobalString('MAIN_UMASK'))
			@chmod($file, octdec(getDolGlobalString('MAIN_UMASK')));

			//var_dump($file,$diroutputpdf,$filename,$pagecount);exit;
		}
		else
		{
			setEventMessage($langs->trans('NoPDFAvailableForChecked'),'errors');
		}
	}

	function generateTBatch($id)
	{
		$TBatch = array();
		$j = 0;
		$qty = 'qtyl'.$id.'_'.$j;
		$batch = 'batchl'.$id.'_'.$j;
		$total_qty = 0;
		$sub_qty = array();
		while (isset($_POST[$batch]))
		{
			// save line of detail into sub_qty
			$sub_qty[$j]['q'] = GETPOST($qty, 'int');	// the qty we want to move for this stock record
			$sub_qty[$j]['id_batch'] = GETPOST($batch, 'int');  // the id into llx_product_batch of stock record to move


			//var_dump($qty);var_dump($batch);var_dump($sub_qty[$j]['q']);var_dump($sub_qty[$j]['id_batch']);
			$total_qty += $sub_qty[$j]['q'];
			$j++;
			$qty = 'qtyl'.$id.'_'.$j;
			$batch = 'batchl'.$id.'_'.$j;
		}

		$TBatch['qty']=$total_qty;
		$TBatch['ix_l']=$id;
		$TBatch['detail']=$sub_qty;


		return $TBatch;
	}

}
