<?php
/**
 * Page de configuration du module Facture TTN
 *
 * @package    Dolibarr
 * @subpackage ModuleFactureTTN
 * @version    1.0.0
 * @author     [A_COMPLETER] <[A_COMPLETER]>
 * @license    GPL-3.0+
 */

// Protection contre l'accès direct non autorisé
if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}
if (!defined('NOLOGIN')) {
    define('NOLOGIN', '1');
}
if (!defined('NOCSRFCHECK')) {
    define('NOCSRFCHECK', '1');
}

require_once '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

// Chargement des traductions
$langs->loadLangs(array('admin', 'facture_ttn@facture_ttn'));

// Vérification des permissions
if (!$user->admin) {
    accessforbidden();
}

// Récupération des variables POST
$action = GETPOST('action', 'aZ09');
$value = GETPOST('value', 'alpha');
$label = GETPOST('label', 'alpha');

// Initialisation des objets
$form = new Form($db);

// En-tête de la page
llxHeader('', $langs->trans('FactureTTNSetup'), '', '', 0, 0, '', '', '', 'modFactureTTN@facture_ttn');

// Affichage du titre et du lien retour
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1">' . $langs->trans('BackToModuleList') . '</a>';
print load_fiche_titre($langs->trans('FactureTTNSetup'), $linkback, 'title_setup');

// Onglets de configuration
$head = array();
$h = 0;
$head[$h][0] = dol_buildpath('/custom/facture_ttn/admin/facture_ttn.php', 1);
$head[$h][1] = $langs->trans('Settings');
$head[$h][2] = 'settings';
$h++;

print dol_get_fiche_head($head, 'settings', $langs->trans('ModuleFactureTTNName'), -1, 'bill');

// Gestion des actions
if ($action == 'update' && !empty($user->admin)) {
    // Sécurisation des données POST
    $schema_ttn = GETPOST('FACTURE_TTN_SCHEMA_VERSION', 'alpha');
    $qr_prefix = GETPOST('FACTURE_TTN_QR_PREFIX', 'alpha');
    $test_mode = GETPOST('FACTURE_TTN_TEST_MODE', 'int');

    // Validation des données (exemples simples)
    $errors = array();

    if (empty($schema_ttn)) {
        $errors[] = $langs->trans('ErrorSchemaVersionRequired');
    }

    if (empty($qr_prefix)) {
        $errors[] = $langs->trans('ErrorQRPrefixRequired');
    }

    if (empty($errors)) {
        // Sauvegarde dans $conf->global
        dolibarr_set_const($db, 'FACTURE_TTN_SCHEMA_VERSION', $schema_ttn, 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'FACTURE_TTN_QR_PREFIX', $qr_prefix, 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'FACTURE_TTN_TEST_MODE', $test_mode, 'chaine', 0, '', $conf->entity);

        setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
    } else {
        setEventMessages(implode('<br>', $errors), null, 'errors');
    }
}

// Affichage du formulaire
print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '" name="setup_form">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Parameter') . '</td>';
print '<td>' . $langs->trans('Value') . '</td>';
print '<td>' . $langs->trans('Help') . '</td>';
print '</tr>';

// Ligne 1 : Version du schéma TTN
print '<tr class="oddeven">';
print '<td><label for="FACTURE_TTN_SCHEMA_VERSION">' . $langs->trans('FactureTTNSchemaVersion') . '</label></td>';
print '<td>';
print '<input type="text" class="flat minwidth150" id="FACTURE_TTN_SCHEMA_VERSION" name="FACTURE_TTN_SCHEMA_VERSION" value="' . dol_escape_htmltag(getDolGlobalString('FACTURE_TTN_SCHEMA_VERSION', '1.0')) . '">';
print '</td>';
print '<td class="opacitymedium">' . $langs->trans('FactureTTNSchemaVersionHelp') . '</td>';
print '</tr>';

// Ligne 2 : Préfixe QR Code
print '<tr class="oddeven">';
print '<td><label for="FACTURE_TTN_QR_PREFIX">' . $langs->trans('FactureTTNQRPrefix') . '</label></td>';
print '<td>';
print '<input type="text" class="flat minwidth150" id="FACTURE_TTN_QR_PREFIX" name="FACTURE_TTN_QR_PREFIX" value="' . dol_escape_htmltag(getDolGlobalString('FACTURE_TTN_QR_PREFIX', 'TTN-')) . '">';
print '</td>';
print '<td class="opacitymedium">' . $langs->trans('FactureTTNQRPrefixHelp') . '</td>';
print '</tr>';

// Ligne 3 : Mode test
print '<tr class="oddeven">';
print '<td><label for="FACTURE_TTN_TEST_MODE">' . $langs->trans('FactureTTNTestMode') . '</label></td>';
print '<td>';
print ajax_constantonoff('FACTURE_TTN_TEST_MODE');
print '</td>';
print '<td class="opacitymedium">' . $langs->trans('FactureTTNTestModeHelp') . '</td>';
print '</tr>';

print '</table>';

print '<div class="center">';
print '<input type="submit" class="button button-save" name="save" value="' . $langs->trans('Save') . '">';
print '</div>';

print '</form>';

print '<br>';

// Section d'information supplémentaire
print load_fiche_titre($langs->trans('MoreInformation'), '', '');
print '<div class="fiche center">';
print $langs->trans('FactureTTNInfoText');
print '</div>';

print dol_get_fiche_end();

// Pied de page
llxFooter();
$db->close();
