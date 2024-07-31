<?php

namespace App\Controller;

use App\Controller\BaseController;

use App\Model\AbsenceReason;
use App\Model\SeparationLine;
use App\PlanningBiblio\Framework;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

require_once(__DIR__ . '/../../public/planning/poste/class.planning.php');
require_once(__DIR__ . '/../../public/absences/class.absences.php');
require_once(__DIR__ . '/../../public/planning/poste/fonctions.php');

class WeekPlanningController extends BaseController
{
    use \App\Trait\PlanningTrait;

    /**
     * @Route("/week", name="planning.week", methods={"GET"})
     */
    public function week(Request $request)
    {
        $groupe = $request->get('groupe');
        $site = $request->get('site');
        $tableau = $request->get('tableau');
        $date = $request->get('date');

        $site = $this->setSite($site);

        $dbprefix = $GLOBALS['dbprefix'];
        $CSRFSession = $GLOBALS['CSRFSession'];

        $date = filter_var($date, FILTER_CALLBACK, array("options"=>"sanitize_dateSQL"));

        if (!$date and array_key_exists('PLdate', $_SESSION)) {
            $date = $_SESSION['PLdate'];
        } elseif (!$date and !array_key_exists('PLdate', $_SESSION)) {
            $date = date("Y-m-d");
        }

        $_SESSION['PLdate'] = $date;
        $_SESSION['week'] = true;

        list($d, $semaine, $semaine3, $jour, $dates, $datesSemaine, $dateAlpha)
            = $this->getDatesPlanning($date);

        global $idCellule;
        $idCellule=0;

        //-------- Vérification des droits de modification (Autorisation) -------------//
        $autorisationN1 = (in_array((300 + $site), $this->permissions)
            or in_array((1000 + $site), $this->permissions));

        // ------ FIN Vérification des droits de modification (Autorisation) -----//

        $fin = $this->config('Dimanche') ? 6 : 5;

        //	Selection des messages d'informations
        $db = new \db();
        $dateDebut = $db->escapeString($dates[0]);
        $dateFin = $db->escapeString($dates[$fin]);
        $db->query("SELECT * FROM `{$dbprefix}infos` WHERE `debut`<='$dateFin' AND `fin`>='$dateDebut' ORDER BY `debut`,`fin`;");
        $messages_infos = null;
        if ($db->result) {
            foreach ($db->result as $elem) {
                $messages_infos[] = $elem['texte'];
            }
            $messages_infos = implode(' - ', $messages_infos);
        }

        // $absence_reasons will be used in the cellule_poste function.
        global $absence_reasons;
        $absence_reasons = $this->entityManager->getRepository(AbsenceReason::class);

        switch ($this->config('nb_semaine')) {
            case 2:
                $type_sem = $semaine % 2 ? 'Impaire' : 'Paire';
                $affSem = "$type_sem ($semaine)";
                break;
            case 3:
                $type_sem = $semaine3;
                $affSem = "$type_sem ($semaine)";
                break;
            default:
                $affSem = $semaine;
                break;
        }

        // Positions, skills...
        $activites = $this->getSkills();
        $categories = $this->getCategories();
        global $postes;
        $postes = $this->getPositions($activites, $categories);

        // Parameters for planning's menu
        // (Calendar widget, days, week and action icons)
        $this->templateParams(array(
            'affSem'            => $affSem,
            'autorisationN1'    => $autorisationN1,
            'content_planning'  => true,
            'CSRFSession'       => $CSRFSession,
            'date'              => $date,
            'dates'             => $dates,
            'day'               => $jour,
            'messages_infos'    => $messages_infos,
            'public_holiday'    => jour_ferie($date),
            'site'              => $site,
            'week_view'         => true,
        ));

        // div id='tabsemaine1' : permet d'afficher les tableaux masqués.
        // La fonction JS afficheTableauxDiv utilise $('#tabsemaine1').after()
        // pour afficher les liens de récupération des tableaux.

        // ---------- FIN Affichage du titre et du calendrier ------------//

        // Lignes de separation
        $lignes_sep = $this->entityManager->getRepository(SeparationLine::class);

        // Pour tous les jours de la semaine
        $days = array();
        for ($j=0;$j<=$fin;$j++) {
            $day = array();
            $date=$dates[$j];
            $day['date'] = $date;

            // ---------- Verrouillage du planning ----------- //
            $perso2 = null;
            $date_validation2 = null;
            $heure_validation2 = null;
            $verrou = false;

            $db = new \db();
            $db->select2('pl_poste_verrou', '*', array('date' => $date, 'site' => $site));
            if ($db->result) {
                $verrou = $db->result[0]['verrou2'];
                $perso = nom($db->result[0]['perso']);
                $perso2 = nom($db->result[0]['perso2']);
                $date_validation = dateFr(substr($db->result[0]['validation'], 0, 10));
                $heure_validation = substr($db->result[0]['validation'], 11, 5);
                $date_validation2 = dateFr(substr($db->result[0]['validation2'], 0, 10));
                $heure_validation2 = substr($db->result[0]['validation2'], 11, 5);
                $validation2 = $db->result[0]['validation2'];
            }
            $day['perso2'] = $perso2;
            $day['date_validation2'] = $date_validation2;
            $day['heure_validation2'] = $heure_validation2;

            // ------------ Choix du tableau ----------- //
            $db = new \db();
            $db->select2('pl_poste_tab_affect', 'tableau', array('date' => $date, 'site' => $site));
            $tab = $db->result ? $db->result[0]['tableau'] : null;

            $day['tab'] = $tab;
            $day['verrou'] = $verrou;
            // ----------- FIN Choix du tableau --------- //

            // ----------- Vérification si le planning est validé ------------ //
            if ($verrou or $autorisationN1) {
                // Looking for info cells.
                // All this infos will be stored in array
                // and used function cellules_postes().

                // $cellules will be used in the cellule_poste function.
                global $cellules;
                $cellules = $this->getCells($date, $site, $activites);                

                // Looking for absences.
                global $absences;
                $absences = $this->getAbsences($date);

                // $conges will be used in the cellule_poste function and added to $absences_planning
                global $conges;
                $conges = $this->getHolidays($date);

                // ------------ Planning display --------------------//
                // Get framework structure, start and end hours.
                list($tabs, $startTime, $endTime) = $this->getFrameworkStructure($tab);

                $hiddenTables = $this->getHiddenTables($tab);

                // TODO A PLACER DANS TRAIT / createTabs
                $l = 0;
                foreach ($tabs as $index => $tab) {

                    $hiddenTable = in_array($l, $hiddenTables) ? 'hidden-table' : null;
                    $tabs[$index]['hiddenTable'] = $hiddenTable;
                    $tabs[$index]['l'] = $l;

                    // Comble les horaires laissés vides : créé la colonne manquante,
                    // les cellules de cette colonne seront grisées.
                    $cellules_grises = array();
                    $tmp = array();

                    // Première colonne : si le début de ce tableau est
                    // supérieur au début d'un autre tableau.
                    $k = 0;
                    if ($tab['horaires'][0]['debut'] > $startTime) {
                        $tmp[] = array(
                            'debut' => $startTime,
                            'fin' => $tab['horaires'][0]['debut']
                        );
                        $cellules_grises[] = $k++;
                    }

                    // Colonnes manquantes entre le début et la fin
                    foreach ($tab['horaires'] as $key => $value) {
                        if ($key == 0 or $value['debut'] == $tab['horaires'][$key-1]['fin']) {
                            $tmp[] = $value;
                        } elseif ($value['debut'] > $tab['horaires'][$key-1]['fin']) {
                            $tmp[] = array(
                                'debut' => $tab['horaires'][$key-1]['fin'],
                                'fin' => $value['debut']
                            );
                            $tmp[] = $value;
                            $cellules_grises[] = $k++;
                        }
                        $k++;
                    }

                    // Dernière colonne : si la fin de ce tableau est
                    // inférieure à la fin d'un autre tableau.
                    $nb = count($tab['horaires']) - 1;
                    if ($tab['horaires'][$nb]['fin'] < $endTime) {
                        $tmp[] = array(
                            'debut' => $tab['horaires'][$nb]['fin'],
                            'fin' => $endTime
                        );
                        $cellules_grises[] = $k;
                    }

                    $tab['horaires'] = $tmp;

                    // Table name
                    $tabs[$index]['titre2'] = $tab['titre'];
                    if (!$tab['titre']) {
                        $tabs[$index]['titre2'] = "Sans nom $sn";
                        $sn++;
                    }

                    // Masquer les tableaux
                    $masqueTableaux = null;
                    if ($this->config('Planning-TableauxMasques')) {
                        // FIXME HTML
                        $masqueTableaux = "<span title='Masquer' class='pl-icon pl-icon-hide masqueTableau pointer noprint' data-id='$l' ></span>";
                    }
                    $tabs[$index]['masqueTableaux'] = $masqueTableaux;

                    // Lignes horaires
                    $colspan = 0;
                    foreach ($tab['horaires'] as $key => $horaires) {

                        $tabs[$index]['horaires'][$key]['start_nb30'] = nb30($horaires['debut'], $horaires['fin']);
                        $tabs[$index]['horaires'][$key]['start_h3'] = heure3($horaires['debut']) ;
                        $tabs[$index]['horaires'][$key]['end_h3'] = heure3($horaires['fin']) ;

                        $colspan += nb30($horaires['debut'], $horaires['fin']);
                    }
                    $tabs[$index]['colspan'] = $colspan;

                    // Lignes postes et grandes lignes
                    foreach ($tab['lignes'] as $key => $ligne) {

                        // Check if the line is empty.
                        // Don't show empty lines if Planning-vides is disabled.
                        $emptyLine = null;
                        if (!$this->config('Planning-lignesVides') and $verrou and isAnEmptyLine($ligne['poste'])) {
                            $emptyLine = "empty-line";
                        }

                        $ligne['emptyLine'] = $emptyLine;
                        $ligne['is_position'] = '';
                        $ligne['separation'] = '';

                        // Position lines
                        if ($ligne['type'] == 'poste' and $ligne['poste']) {

                            $ligne['is_position'] = 1;

                            // FIXME 'classTD' and 'classTR' are always the same.
                            // FIXME Check if 'classTD' is used

                            // Cell class depends if the position is mandatory or not.
                            $ligne['classTD'] = $postes[$ligne['poste']]['obligatoire'] == 'Obligatoire' ? 'td_obligatoire' : 'td_renfort';

                            // Line class depends if the position is mandatory or not.
                            $ligne['classTR'] = $postes[$ligne['poste']]['obligatoire'] == 'Obligatoire' ? 'tr_obligatoire' : 'tr_renfort';

                            // Line class depends on skills and categories.
                            $ligne['classTR'] .= ' ' . $postes[$ligne['poste']]['classes'];

                            // Classe de la ligne en fonction des activités et des catégories
                            $ligne['position_name'] = $postes[$ligne['poste']]['nom'];
                            $ligne['position_floor'] = $postes[$ligne['poste']]['etage'];

                            $i=1;
                            $k=1;
                            $ligne['line_time'] = array();
                            foreach ($tab['horaires'] as $horaires) {
                                // Recherche des infos à afficher dans chaque cellule

                                // Cell disabled.
                                // Cellules grisées si définies dans la configuration
                                // du tableau et si la colonne a été ajoutée automatiquement.
                                $horaires['disabled'] = 0;

                                if (in_array("{$ligne['ligne']}_{$k}", $tab['cellules_grises']) or in_array($i-1, $cellules_grises)) {                                             
                                    $horaires['disabled'] = 1;
                                    $horaires['colspan'] = nb30($horaires['debut'], $horaires['fin']);

                                    // If column added, that shift disabled cells.
                                    // Si colonne ajoutée, ça décale les cellules grises initialement prévues.
                                    // On se décale d'un cran en arrière pour rétablir l'ordre.
                                    if (in_array($i - 1, $cellules_grises)) {
                                        $k--;
                                    }
                                }
                                
                                // function cellule_poste(date,debut,fin,colspan,affichage,poste,site)
                                else {
                                    $horaires['position_cell'] = cellule_poste($date, $horaires['debut'], $horaires['fin'], nb30($horaires['debut'], $horaires['fin']), 'noms', $ligne['poste'], $site);
                                }
                                $i++;
                                $k++;
                                $ligne['line_time'][] = $horaires;
                            }
                        }

                        // Separation lines
                        if ($ligne['type'] == 'ligne') {
                            $ligne['separation'] = $lignes_sep->find($ligne['poste'])->nom();
                        }

                        $tabs[$index]['lignes'][$key] = $ligne;
                    }
                    $l++;
                }
                // TODO FIN A PLACER DANS TRAIT / createTabs
                $day['tabs'] = $tabs;
            }
            // TODO : dans le template, remplacer line_sep par separation

            // Notes : Affichage
            $p = new \planning();
            $p->date = $date;
            $p->site = $site;
            $p->getNotes();
            $notes = $p->notes;
            $notesDisplay = trim($notes) ? null : "style='display:none;'";
            $day['notes'] = $notes;
            $day['notesDisplay'] = $notesDisplay;
            $days[] = $day;
        }

        $this->templateParams(array(
            'days' => $days
        ));

        return $this->output('planning/poste/week.html.twig');
    }
}
