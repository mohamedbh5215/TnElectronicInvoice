<?php
/**
 * Description of module Facture TTN (Tunisie Telecom Network / E-invoicing)
 *
 * @package    Dolibarr
 * @subpackage ModuleFactureTTN
 * @version    1.0.0
 * @author     [A_COMPLETER] <[A_COMPLETER]>
 * @license    GPL-3.0+
 * @link       https://www.dolibarr.org
 */

require_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

/**
 * Class modFactureTTN
 * Descripteur du module personnalisé facture_ttn
 */
class modFactureTTN extends DolibarrModules
{
    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;

        // Id unique du module (doit être unique, entier, > 0)
        $this->numero = 550001; // [A_COMPLETER] Vérifier que cet ID est libre dans votre instance

        // Nom technique du module (sans espaces, en minuscules)
        $this->name = preg_replace('/^mod/i', '', get_class($this));

        // Description du module
        $this->description = "Module de facturation électronique tunisienne (TTN). Ajoute un onglet de configuration et une table de traçabilité.";

        // URL vers la documentation du module
        $this->doc_url = "https://wiki.dolibarr.org/index.php/Module_facture_ttn"; // [A_COMPLETER]

        // Texte de description si la langue n'est pas chargée
        if (!isset($langs) || empty($langs->load("facture_ttn@facture_ttn"))) {
            $langs->load("facture_ttn@facture_ttn");
        }

        // Nom affiché dans les menus (clé de langue)
        $this->editor_name = 'Custom Developer'; // [A_COMPLETER]
        $this->editor_url = 'https://example.com'; // [A_COMPLETER]

        // Version du module
        $this->version = '1.0.0';

        // Type de version : 'development', 'experimental', 'dolibarr', 'experimental_dolibarr' ou 'stable'
        $this->version = 'development'; // [A_COMPLETER] à passer en 'stable' en production

        // Clé de langue pour la description longue
        $this->descriptionlong = "Ce module permet de gérer la conformité avec le système de facturation électronique tunisien (TTN). Il inclut une page de configuration admin, une table de log de traçabilité et des hooks sur les factures.";

        // Icône du module (chemin relatif depuis htdocs/theme/img/)
        $this->picto = 'bill';

        // Dépendances
        $this->depends = ['modFacture']; // Le module Facture client doit être activé
        $this->requiredby = []; // Aucun module ne dépend de celui-ci pour l'instant
        $this->conflictwith = []; // Pas de conflit connu
        $this->langfiles = ["facture_ttn@facture_ttn"];

        // Constantes nécessaires au module (seront créées à l'activation)
        $this->const = [];

        // Tables créées par le module
        $this->tables = ['facture_ttn_log'];

        // Fichiers SQL d'initialisation (optionnel)
        // $this->sql = array();

        // Hooks injectés par le module
        $this->hooks = array(
            'invoicecard',
            'invoicelist',
            'toprightmenu'
        );

        // Permissions par défaut
        $this->rights_class = 'facture_ttn';

        // Options : permet d'activer/désactiver des fonctionnalités via $conf->global
        $this->module_parts = array(
            'triggers' => 1, // Active la gestion des triggers
            'css' => array(),
            'js' => array(),
            'tpl' => 0,
            'theme' => 0,
            'substitutions' => 0,
            'menus' => 0,
            'login' => 0,
            'societe' => 0,
            'barcode' => 0,
            'models' => 0,
            'pdfmodels' => 0,
            'pageheaders' => 0,
            'workflow' => 0,
            'dir' => array('output' => 'temp/facture_ttn'),
            'objecttrigger' => array('facture' => 'interface_99_modFactureTTN_FactureTTN')
        );
    }

    /**
     * Fonction appelée lors de l'activation du module
     *
     * @param  string $options Options d'activation
     * @return int             1 si OK, <0 si KO
     */
    public function init($options = '')
    {
        global $langs, $db;

        $result = $this->_load_tables('/facture_ttn/sql/');

        if ($result < 0) {
            return -1;
        }

        // Création des constantes globales si nécessaire
        $constantes = array(
            'FACTURE_TTN_ENABLED' => array('chaine', '1', 'Module activé', 0, 0),
            'FACTURE_TTN_TEST_MODE' => array('chaine', '0', 'Mode test activé', 0, 0),
            'FACTURE_TTN_SCHEMA_VERSION' => array('chaine', '1.0', 'Version du schéma TTN', 0, 0),
            'FACTURE_TTN_QR_PREFIX' => array('chaine', 'TTN-', 'Préfixe QR Code', 0, 0),
        );

        foreach ($constantes as $key => $val) {
            if (!dolibarr_set_const($db, $key, $val[1], $val[0], $val[3], $val[4])) {
                dol_syslog(__METHOD__ . "Erreur lors de la création de la constante " . $key, LOG_ERR);
                return -1;
            }
        }

        return parent::init($options);
    }

    /**
     * Fonction appelée lors de la désactivation du module
     *
     * @param  string $options Options de désactivation
     * @return int             1 si OK, <0 si KO
     */
    public function remove($options = '')
    {
        global $db;

        // Suppression des constantes
        $constantes = array(
            'FACTURE_TTN_ENABLED',
            'FACTURE_TTN_TEST_MODE',
            'FACTURE_TTN_SCHEMA_VERSION',
            'FACTURE_TTN_QR_PREFIX',
        );

        foreach ($constantes as $key) {
            dolibarr_del_const($db, $key, 0);
        }

        // Note: La table llx_facture_ttn_log n'est pas supprimée automatiquement pour conserver l'historique.
        // Si vous souhaitez la supprimer, décommentez la ligne suivante :
        // $this->_delete_tables();

        return parent::remove($options);
    }
}
