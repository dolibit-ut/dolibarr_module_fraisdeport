<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2013 ATM Consulting <support@atm-consulting.fr>
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
 * 	\file		core/triggers/interface_99_modMyodule_Mytrigger.class.php
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
class InterfaceFraisdeport
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
        $this->picto = 'mymodule@mymodule';
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
        // Put here code you want to execute when a Dolibarr business events occurs.
        // Data and type of action are stored into $object and $action
        // Users
        if ($action == 'ORDER_VALIDATE' || $action == 'PROPAL_VALIDATE') {
        	
			global $db,$conf;

			$langs->load('fraisdeport@fraisdeport');
			
			$object->fetch_optionals($object->id);
			/*echo "<pre>";
			print_r($object);
			echo "</pre>";*/
				
			dol_include_once('core/lib/admin.lib.php');
					
			// On récupère les frais de port définis dans la configuration du module
			$TFraisDePort = unserialize($conf->global->FRAIS_DE_PORT_ARRAY);
            $TFraisDePortWeight = unserialize($conf->global->FRAIS_DE_PORT_WEIGHT_ARRAY);
            $fk_product = $conf->global->FRAIS_DE_PORT_ID_SERVICE_TO_USE;
			
			// On vérifie s'il n'y a pas déjà les frais de port dans le document (double validation ou ajout manuel...)
			$fdpAlreadyInDoc = false;
			foreach($object->lines as $line) {
				if(!empty($line->fk_product) && $line->fk_product == $fk_product) {
					$fdpAlreadyInDoc = true;
                    break;
				}
			}
			
			if(!$fdpAlreadyInDoc && !empty($fk_product) && $object->array_options['options_use_frais_de_port'] === 'Oui') {
			     dol_include_once('/product/class/product.class.php','Product');
                
				// On les range du pallier le plus petit au plus grand
				ksort($TFraisDePort);
	
				// On parcoure les pallier du plus petit au plus grand pour chercher si le montant de la commande est inférieur à l'un des palliers
				$fdp_used_montant = 0;
				if(is_array($TFraisDePort) && count($TFraisDePort) > 0) {
					foreach ($TFraisDePort as $pallier => $fdp) {
						if($object->total_ht < $pallier) {
							$fdp_used_montant = $fdp;
							break;
						}
					}
				}
				
                $fdp_used_weight = 0;
                if($conf->global->FRAIS_DE_PORT_USE_WEIGHT) {
                    $total_weight = 0;
                    foreach($object->lines as &$line) {
                        if($line->fk_product_type ==0 && $line->fk_product>0 ) {
                            $p=new Product($db);
                            $p->fetch($line->fk_product);
                            
                            if($p->id>0) {
                                $weight_kg = $p->weight * $line->qty * pow(10, $p->weight_units);
                                $total_weight+=$weight_kg;
                            }
                        }
                    }
                    
                    if(is_array($TFraisDePortWeight) && count($TFraisDePortWeight) > 0) {
                        foreach ($TFraisDePortWeight as $fdp) {
                            if($total_weight >= $fdp['weight'] && ($fdp['fdp']>$fdp_used_weight || empty($fdp_used_weight) ) ) {
                                if (empty($fdp['zip']) 
                                    || (!empty( $fdp['zip'] ) && strpos( $object->client->zip, $fdp['zip']) === 0 ) ){
                                        $fdp_used_weight = $fdp['fdp'];        
                                    } 
                            }
                        }
                    }
                }
               
                $fdp_used = max($fdp_used_weight, $fdp_used_montant );
                $p = new Product($db);
				$p->fetch($fk_product);
				$object->statut = 0;
				
				$used_tva = ($object->client->country_id > 1) ? 0 : $p->tva_tx;
				
				if($object->element == 'commande') {
					$object->addline("Frais de port", $fdp_used, 1, $used_tva, 0, 0, $fk_product, 0, 0, 0, 'HT', 0, '', '', $p->type);
				} else if($object->element == 'propal') {
					$object->addline("Frais de port", $fdp_used, 1, $used_tva, 0, 0, $fk_product, 0, 'HT', 0, 0, $p->type);
				}
                
                setEventMessage($langs->trans('PortTaxAdded').' : '.price($fdp_used).$conf->currency.' '.$langs->trans('VAT').' '.$used_tva.'%' );
				
				$object->fetch($object->id);
				$object->statut = 1; // TODO à quoi ça sert... Puisqu'il n'ya pas de save... :-|
			
				
			}
			
            dol_syslog(
                "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
            );
        }

        return 0;
    }
}
