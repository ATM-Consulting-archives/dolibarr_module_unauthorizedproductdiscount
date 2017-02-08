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
 * 	\file		core/triggers/interface_99_modMyodule_unauthorizedproductdiscounttrigger.class.php
 * 	\ingroup	unauthorizedproductdiscount
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
class Interfaceunauthorizedproductdiscounttrigger
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
        $this->family = "demo";
        $this->description = "Triggers of this module are empty functions."
            . "They have no effect."
            . "They are provided for tutorial purpose only.";
        // 'development', 'experimental', 'dolibarr' or version
        $this->version = 'development';
        $this->picto = 'unauthorizedproductdiscount@unauthorizedproductdiscount';
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
     * Function called when a Dolibarrr business event is done.
     * All functions "run_trigger" are triggered if file
     * is inside directory core/triggers
     *
     * 	@param		string		$action		Event action code
     * 	@param		Object		$object		Object
     * 	@param		User		$user		Object user
     * 	@param		Translate	$langs		Object langs
     * 	@param		conf		$conf		Object conf
     * 	@return		int						<0 if KO, 0 if no triggered ran, >0 if OK
     */
    public function run_trigger($action, $object, $user, $langs, $conf)
    {
    	global $db, $conf;

		// Ce trigger ne doit être déclenché que si l'on est sur du addline ou updateline des éléments en conf
		if(!in_array($action, array('LINEPROPAL_INSERT', 'LINEPROPAL_UPDATE', 'LINEBILL_INSERT', 'LINEBILL_UPDATE')) && empty($object->fk_product)) return 0;
        
		define('INC_FROM_DOLIBARR', true);
		require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
		require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
		
		// Chargement du produit
		$product = new Product($db);
		$product->fetch($object->fk_product);
		if(empty($product->array_options['options_remise_interdite'])) return 0;
		
		// Chargement de l'objet parent (propal, cmd ou facture)
		if(get_class($object) === 'PropaleLigne') {
			$o = new Propal($db);
			$o->fetch($object->fk_propal);
		} elseif(get_class($object) === 'FactureLigne') {
			$o = new Facture($db);
			$o->fetch($object->fk_facture);
		}
		
		// On vide le trigger du module pour ne pas faire de boucle infinie (impossible de passer un notrigger à la fonction updateline, on ne peut la passer qu'à la fonction update de l'objet line)
		$conf->modules_parts['triggers']['unauthorizedproductdiscount'] = '';
		
		$msg = false;
		
        if ($action === 'LINEPROPAL_INSERT' || $action === 'LINEPROPAL_UPDATE') {
        	
			if(!empty($conf->global->UNAUTHORIZED_PROD_DISCOUNT_ON_PROPAL)) {
				$o->updateline($object->rowid, ($object->subprice > $product->price) ? $object->subprice : $product->price, $object->qty, 0, $product->tva_tx, 0, 0, $object->desc, 'HT', 0, 0, 0, 0, 0, $object->pa_ht, '', $object->product_type);
				$msg=true;
			}
			
        } elseif($action === 'LINEBILL_INSERT' || $action === 'LINEBILL_UPDATE') {
        	
			if(!empty($conf->global->UNAUTHORIZED_PROD_DISCOUNT_ON_PROPAL)) {
				$o->updateline($object->rowid, $object->desc, ($object->subprice > $product->price) ? $object->subprice : $product->price, $object->qty, 0, '', '', $product->tva_tx, 0, 0, 'HT', 0, $object->product_type, 0, 0, $object->fk_fournprice, $object->pa_ht, $object->label);
				$msg=true;
			}
			
        }
		
		if($msg) setEventMessage('Prix ajusté car remise non autorisée', 'warnings');
		
        dol_syslog(
            "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
        );

        return 0;
    }
}