<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\file		core/triggers/interface_99_modShippableOrder_ShippableOrdertrigger.class.php
 * 	\ingroup	mymodule
 * 	\brief		Sample trigger
 * 	\remarks	You can create other triggers by copying this one
 * 				- File name should be either:
 * 					interface_99_modMymodule_Mytrigger.class.php
 * 					interface_99_all_Mytrigger.class.php
 * 				- The file must stay in core/triggers
 * 				- The class name must be InterfaceMytrigger
 * 				- The constructor method must be named InterfaceMytrigger
 * 				- The name property name must be Mytrigger
 */

/**
 * Trigger class
 */
class InterfaceShippableOrdertrigger
{

    private $db;

    /**
     * Constructor
     *
     * 	@param		DoliDB		$db		Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "ATM Consulting - CRM";
        $this->description = "Triggers of this module are empty functions."
            . "They have no effect."
            . "They are provided for tutorial purpose only.";
        // 'development', 'experimental', 'dolibarr' or version
        $this->version = 'development';
        $this->picto = 'shippableorder@shippableorder';
    }

    /**
     * Trigger name
     *
     * 	@return		string	Name of trigger file
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Trigger description
     *
     * 	@return		string	Description of trigger file
     */
    public function getDesc()
    {
        return $this->description;
    }

    /**
     * Trigger version
     *
     * 	@return		string	Version of trigger file
     */
    public function getVersion()
    {
        global $langs;
        $langs->load("admin");

        if ($this->version == 'development') {
            return $langs->trans("Development");
        } elseif ($this->version == 'experimental')

                return $langs->trans("Experimental");
        elseif ($this->version == 'dolibarr') return DOL_VERSION;
        elseif ($this->version) return $this->version;
        else {
            return $langs->trans("Unknown");
        }
    }

    /**
	/**
	 * Executes actions based on Dolibarr business events.
	 *
	 * Specifically, when the 'SHIPPING_VALIDATE' action is triggered,
	 * it checks if the order associated with the shipping is fully shipped.
	 * If so, and if 'SHIPPABLEORDER_CLOSE_ORDER' is enabled, the order is closed.
	 *
	 * @param string $action Event action code (e.g., 'SHIPPING_VALIDATE').
	 * @param Object $object The object related to the event (e.g., Expedition object for SHIPPING_VALIDATE).
	 * @param User $user User object.
	 * @param Translate $langs Language object.
	 * @param conf $conf Configuration object.
	 * @return int <0 if KO, 0 if no triggered ran, >0 if OK.
	 */
    public function runTrigger($action, $object, $user, $langs, $conf)
    {
		if ($action == 'SHIPPING_VALIDATE') {

			// Classer délivrée la commande si totalement expédiée
			if(getDolGlobalString('SHIPPABLEORDER_CLOSE_ORDER')) {
				dol_include_once('/commande/class/commande.class.php');
				dol_include_once('/shippableorder/class/shippableorder.class.php');

				$object->fetchObjectLinked(0,'commande');

				if(!empty($object->linkedObjects['commande'])){
					$commande = array_pop($object->linkedObjects['commande']);

					$orderShippable = new ShippableOrder($this->db);
					$orderShippable->isOrderShippable($commande->id);

					if($orderShippable->nbProduct == 0) {
						$commande->cloture($user);
						setEventMessage($langs->trans('OrderClosed',$commande->ref));
					}
				}
			}

			dol_syslog(
				"Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
			);
		}

		return 0;
	}
}
