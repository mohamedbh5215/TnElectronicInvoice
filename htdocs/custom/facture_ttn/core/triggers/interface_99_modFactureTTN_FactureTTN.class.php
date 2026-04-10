<?php
/**
 * Trigger pour le module Facture TTN
 * Hook sur la validation des factures pour initialiser une entrée dans la table de log
 *
 * @package    Dolibarr
 * @subpackage ModuleFactureTTN
 * @version    1.0.0
 * @author     [A_COMPLETER] <[A_COMPLETER]>
 * @license    GPL-3.0+
 */

require_once DOL_DOCUMENT_ROOT . '/core/triggers/interface.class.php';

/**
 * Classe Interface_99_modFactureTTN_FactureTTN
 * Gestion des triggers pour le module facture_ttn
 */
class Interface_99_modFactureTTN_FactureTTN implements iTrigger
{
    /**
     * Nom du trigger (doit correspondre au nom du fichier sans interface_)
     * @var string
     */
    public $name;

    /**
     * Descriptif du trigger
     * @var string
     */
    public $description;

    /**
     * Version du trigger
     * @var string
     */
    public $version;

    /**
     * Famille du trigger
     * @var string
     */
    public $family;

    /**
     * Visibilité du trigger (1 = visible dans l'interface admin)
     * @var int
     */
    public $picto = 'bill';

    /**
     * Ordre d'exécution du trigger
     * @var int
     */
    public $order = 99;

    /**
     * Types d'événements gérés par ce trigger
     * @var array
     */
    public $triggers = array(
        'BILL_VALIDATE',
        'BILL_CREATE',
        'BILL_DELETE'
    );

    /**
     * Constructeur
     *
     * @param DoliDB $db Handler de base de données
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->name = preg_replace('/^Interface_/', '', get_class($this));
        $this->description = "Trigger du module Facture TTN : Crée une entrée dans llx_facture_ttn_log lors de la validation d'une facture.";
        $this->version = '1.0.0';
        $this->family = 'facture_ttn';
    }

    /**
     * Fonction exécutée lorsque le trigger est appelé
     *
     * @param  string $action   Action en cours
     * @param  object $object   Objet métier concerné (Facture)
     * @param  User   $user     Utilisateur connecté
     * @param  Translate $langs Chargement des traductions
     * @param  Conf   $conf     Configuration globale
     * @return int              1 si OK, <0 si KO
     */
    public function runTrigger($action, $object, $user, $langs, $conf)
    {
        global $db;

        // Vérifier que le module est activé
        if (empty($conf->facture_ttn) || empty($conf->global->FACTURE_TTN_ENABLED)) {
            return 0;
        }

        dol_syslog('Trigger: ' . $this->name . ' execute action=' . $action, LOG_DEBUG);

        // Selon l'action demandée
        switch ($action) {
            case 'BILL_VALIDATE':
                return $this->onBillValidate($object, $user, $langs, $conf);
                break;

            case 'BILL_CREATE':
                return $this->onBillCreate($object, $user, $langs, $conf);
                break;

            case 'BILL_DELETE':
                return $this->onBillDelete($object, $user, $langs, $conf);
                break;

            default:
                return 0;
        }
    }

    /**
     * Gestion de l'événement BILL_VALIDATE
     * Crée une entrée dans llx_facture_ttn_log avec le statut PENDING
     *
     * @param  Facture $object Objet facture validée
     * @param  User    $user   Utilisateur connecté
     * @param  Translate $langs Chargement des traductions
     * @param  Conf    $conf   Configuration globale
     * @return int             1 si OK, <0 si KO
     */
    private function onBillValidate($object, $user, $langs, $conf)
    {
        global $db;

        // Vérifier que l'objet est bien une facture
        if (!isset($object->id) || empty($object->id)) {
            dol_syslog('Trigger: ' . $this->name . ' - Facture ID invalide', LOG_WARNING);
            return 0;
        }

        $facture_id = (int) $object->id;

        // Vérifier si une entrée existe déjà pour cette facture
        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "facture_ttn_log";
        $sql .= " WHERE fk_facture = " . $facture_id;

        $resql = $db->query($sql);
        if ($resql === false) {
            dol_syslog('Trigger: ' . $this->name . ' - Erreur SQL: ' . $db->lasterror(), LOG_ERR);
            setEventMessages($langs->trans('ErrorTTNLogCheckFailed'), null, 'errors');
            return -1;
        }

        $existing = $db->fetch_object($resql);
        $db->free($resql);

        // Si une entrée existe déjà, on ne fait rien (ou on pourrait mettre à jour)
        if ($existing) {
            dol_syslog('Trigger: ' . $this->name . ' - Entrée déjà existante pour facture ID=' . $facture_id, LOG_DEBUG);
            return 0;
        }

        // Préparer les données pour l'insertion
        $now = dol_now();
        $status_ttn = 'PENDING';
        $fk_user = (int) $user->id;

        // Génération d'un hash vide pour l'instant (sera mis à jour lors de l'export réel)
        $file_hash = '';
        $exported_file = '';
        $notes = 'Créé automatiquement lors de la validation de la facture';

        // Insertion dans la table llx_facture_ttn_log
        $sql_insert = "INSERT INTO " . MAIN_DB_PREFIX . "facture_ttn_log (";
        $sql_insert .= "fk_facture, status_ttn, file_hash, exported_file, date_export, fk_user, notes, date_creation";
        $sql_insert .= ") VALUES (";
        $sql_insert .= "  " . $facture_id . ",";
        $sql_insert .= "  '" . $db->escape($status_ttn) . "',";
        $sql_insert .= "  '" . $db->escape($file_hash) . "',";
        $sql_insert .= "  '" . $db->escape($exported_file) . "',";
        $sql_insert .= "  NULL,"; // date_export sera renseignée lors de l'export réel
        $sql_insert .= "  " . $fk_user . ",";
        $sql_insert .= "  '" . $db->escape($notes) . "',";
        $sql_insert .= "  '" . $db->idate($now) . "'";
        $sql_insert .= ")";

        $resql_insert = $db->query($sql_insert);
        if ($resql_insert === false) {
            dol_syslog('Trigger: ' . $this->name . ' - Erreur INSERT: ' . $db->lasterror(), LOG_ERR);
            setEventMessages($langs->trans('ErrorTTNLogInsertFailed'), null, 'errors');
            return -1;
        }

        $new_rowid = $db->last_insert_id(MAIN_DB_PREFIX . 'facture_ttn_log');

        dol_syslog('Trigger: ' . $this->name . ' - Entrée créée avec rowid=' . $new_rowid . ' pour facture ID=' . $facture_id, LOG_INFO);

        // Optionnel : ajouter un message à l'utilisateur
        // setEventMessages($langs->trans('TTNLogEntryCreated'), null, 'mesgs');

        return 1;
    }

    /**
     * Gestion de l'événement BILL_CREATE
     * Pour l'instant, ne fait rien mais peut être étendu
     *
     * @param  Facture $object Objet facture créé
     * @param  User    $user   Utilisateur connecté
     * @param  Translate $langs Chargement des traductions
     * @param  Conf    $conf   Configuration globale
     * @return int             0 (pas d'action)
     */
    private function onBillCreate($object, $user, $langs, $conf)
    {
        dol_syslog('Trigger: ' . $this->name . ' - BILL_CREATE detected for facture ID=' . (isset($object->id) ? $object->id : 'N/A'), LOG_DEBUG);
        return 0;
    }

    /**
     * Gestion de l'événement BILL_DELETE
     * Supprime l'entrée correspondante dans llx_facture_ttn_log
     *
     * @param  Facture $object Objet facture supprimé
     * @param  User    $user   Utilisateur connecté
     * @param  Translate $langs Chargement des traductions
     * @param  Conf    $conf   Configuration globale
     * @return int             1 si OK, <0 si KO
     */
    private function onBillDelete($object, $user, $langs, $conf)
    {
        global $db;

        if (!isset($object->id) || empty($object->id)) {
            return 0;
        }

        $facture_id = (int) $object->id;

        // Suppression de l'entrée dans le log TTN
        $sql = "DELETE FROM " . MAIN_DB_PREFIX . "facture_ttn_log";
        $sql .= " WHERE fk_facture = " . $facture_id;

        $resql = $db->query($sql);
        if ($resql === false) {
            dol_syslog('Trigger: ' . $this->name . ' - Erreur DELETE: ' . $db->lasterror(), LOG_ERR);
            return -1;
        }

        dol_syslog('Trigger: ' . $this->name . ' - Entrée supprimée pour facture ID=' . $facture_id, LOG_INFO);

        return 1;
    }
}
