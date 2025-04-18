<?php
/**
Planning Biblio
Licence GNU/GPL (version 2 et au dela)
Voir les fichiers README.md et LICENSE

@file public/planning/poste/class.planning.php
@author Jérôme Combes <jerome@planningbiblio.fr>

Description :
Classe planning

Utilisée par les fichiers du dossier "planning/poste"
*/

use App\Model\Agent;
use App\Model\AbsenceReason;
use App\Model\Position;

// pas de $version=acces direct aux pages de ce dossier => Accès refusé
$version = $GLOBALS['version'] ?? null;

if (!isset($version)) {
    include_once "../../include/accessDenied.php";
}


class planning
{
    public $CSRFToken = null;
    public $date=null;
    public $site=1;
    public $categorieA=false;
    public $elements=array();
    public $menudiv=null;
    public $notes=null;
    public $notesTextarea=null;
    public $validation=null;


    public function fetch()
    {
        if (!$this->date) {
            return;
        }

        $db=new db();
        $db->select2("pl_poste", "*", array("date"=>$this->date, "site"=>$this->site));
        if ($db->result) {
            $tab=array();
            foreach ($db->result as $elem) {
                $tab[$elem['id']]=$elem;
            }
            $this->elements=$tab;
        }
    }
  
  
    // Recherche les agents de catégorie A en fin de service
    public function finDeService()
    {
        $date=$this->date;
        $site=$this->site;

        // Sélection du tableau utilisé
        $db=new db();
        $db->select("pl_poste_tab_affect", "tableau", "date='$date' AND site='$site'");
        $tableau=$db->result[0]["tableau"];

        // Sélection de l'heure de fin
        $db=new db();
        $db->select("pl_poste_horaires", "MAX(fin) AS maxFin", "numero='$tableau'");
        $fin=$db->result[0]["maxFin"];

        // Sélection des agents en fin de service
        $perso_ids=array();
        $db=new db();
        $db->select("pl_poste", "perso_id", "fin='$fin' and site='$site' and `date`='$date' and supprime='0' and absent='0'");
        if ($db->result) {
            foreach ($db->result as $elem) {
                $perso_ids[]=$elem['perso_id'];
            }
        }
        if (empty($perso_ids)) {
            return false;
        }
        $perso_ids=implode(",", $perso_ids);

        // Sélection des statuts des agents en fin de service
        $statuts=array();
        $db=new db();
        $db->select("personnel", "statut", "id IN ($perso_ids)");
        if ($db->result) {
            foreach ($db->result as $elem) {
                if (in_array($elem['statut'], $statuts)) {
                    continue;
                }
                $statuts[]=$elem['statut'];
            }
        }
        if (empty($statuts)) {
            return false;
        }
        $statuts=implode("','", $statuts);

        // Recherche des statuts de catégorie A parmis les statuts fournis
        $db=new db();
        $db->select("select_statuts", "*", "valeur IN ('$statuts') AND categorie='1'");
        if ($db->result) {
            $this->categorieA=true;
        }
    }

    // Affiche la liste des agents dans le menudiv
    public function menudivAfficheAgents($poste, $agents, $date, $debut, $fin, $deja, $quotaSP, $nbAgents, $sr_init, $hide, $deuxSP, $motifExclusion, $absences_non_validees, $journey, $absences_journey)
    {
        $config=$GLOBALS['config'];
        $dbprefix=$config['dbprefix'];
        $d=new datePl($date);
        $j1=$d->dates[0];
        $j7=$d->dates[6];
        $semaine=$d->semaine;
        $semaine3=$d->semaine3;
        $site=$this->site;

        if ($hide) {
            $display="display:none;";
            $groupe_hide=null;
            $classTrListe="tr_liste";
        } else {
            $display=null;
            $groupe_hide="groupe_tab_hide();";
            $classTrListe=null;
        }

        $menudiv = array();
        $menudiv['agents'] = array();
    
        // Calcul des heures de SP à effectuer pour tous les agents
        $heuresSP=calculHeuresSP($date, $this->CSRFToken);

        // Nombre d'heures de la cellule choisie
        $hres_cellule = 0;
        if ($quotaSP) {    // vérifier si le poste est compté dans les stats
            $hres_cellule = diff_heures($debut, $fin, "decimal");
        }
    
        // Calcul des heures d'absences afin d'ajuster les heures de SP
        $a=new absences();
        $a->CSRFToken = $this->CSRFToken;
        $heuresAbsencesTab=$a->calculHeuresAbsences($date);

        if (is_array($agents)) {
            usort($agents, "cmp_nom_prenom");
    
            // Calcul des heures faites ce jour, cette seamine et sur les 4 dernières semaines pour tous les agents
            // Liste des ID des agents pour la requête des heures faites
            $ids = array();
            foreach ($agents as $elem) {
                $ids[]=$elem['id'];
            }
            $agents_liste = implode(",", $ids);
      
            // Intervalle de dates par défaut : la semaine en cours
            $date1 = $j1;
            $date2 = $j7;
      
            // Si l'option hres4semaines est cochée, l'intervalle est de 4 semaines
            if ($config['hres4semaines']) {
                $date1=date("Y-m-d", strtotime("-3 weeks", strtotime($j1)));
            }

            // Recherche des absences dans la table absences pour les déduire des heures faites
            $a=new absences();
            $a->valide = true;
            $a->documents = false;
            $a->fetch("`nom`,`prenom`,`debut`,`fin`", null, $date1." 00:00:00", $date2." 23:59:59");
            $absencesDB=$a->elements;

            // Recherche des postes occupés dans la base avec le plus grand intervalle pour limiter les requêtes
            $db_heures = new db();
            $db_heures->selectInnerJoin(
                array("pl_poste","poste"),
                array("postes","id"),
                array("date","debut","fin","perso_id", "poste"),
                array(),
                array(
                    'perso_id' => "IN $agents_liste",
                    'absent'   => "<>1",
                    'date'     => "BETWEEN {$date1} AND {$date2}",
                ),
                array('quota_sp' => '1')
      );

            if ($db_heures->result) {

                $positions = $GLOBALS['entityManager']->getRepository(Position::class);

                // Pour chaqe résultat, on ajoute le nombre d'heures correspondant à l'agent concerné, pour le jour, la semaine et/ou les 4 semaines
                foreach ($db_heures->result as $elem) {

                    // Vérifie à partir de la table absences si l'agent est absent
                    // S'il est absent, on passe (continue 2)
                    foreach ($absencesDB as $a) {
                        if ($elem['perso_id']==$a['perso_id'] and $a['debut']< $elem['date'].' '.$elem['fin'] and $a['fin']> $elem['date']." ".$elem['debut']) {
                            continue 2;
                        }
                    }

                    if ($positions->find($elem['poste'])->lunch()) {
                        continue;
                    }

                    // Calcul des heures de service public pour affichage à côté du nom des agents
                    if ($GLOBALS['config']['Planning-Heures']) {
                        $h = diff_heures($elem['debut'], $elem['fin'], "decimal");
                        $hres_jour = $elem['date'] == $date ? $h : 0;
                        $hres_semaine = ($elem['date'] >= $j1 and $elem['date'] <= $j7) ? $h : 0;
                        $hres_4sem = $h;
            
                        if (!isset($heures[$elem['perso_id']])) {
                            $heures[$elem['perso_id']] = array("jour"=>$hres_jour, "semaine"=>$hres_semaine, "4semaines"=>$hres_4sem);
                        } else {
                            $heures[$elem['perso_id']]["jour"] += $hres_jour;
                            $heures[$elem['perso_id']]["semaine"] += $hres_semaine;
                            $heures[$elem['perso_id']]["4semaines"] += $hres_4sem;
                        }
                    }
                }
            }
      
            // Recherche des sans repas en dehors de la boucle pour optimiser les performances (juillet 2016)
            $p = new planning();
            $sansRepas = $p->sansRepas($date, $debut, $fin, $poste);

            foreach ($agents as $elem) {
                // Heures hebdomadaires (heures à faire en SP)
                $heuresHebdo=$heuresSP[$elem['id']];
                $heuresHebdoTitle="Quota hebdomadaire";
        
                // Heures hebdomadaires avec prise en compte des absences
                if ($config["Planning-Absences-Heures-Hebdo"] and array_key_exists($elem['id'], $heuresAbsencesTab)) {
                    $heuresAbsences=$heuresAbsencesTab[$elem['id']];
                    if (is_numeric($heuresAbsences)) {
                        if ($heuresAbsences>0) {
                            // On informe du pourcentage sur les heures d'absences
                            $pourcent=null;
                            if (strpos($elem["heures_hebdo"], "%") and $elem["heures_hebdo"]!="100%") {
                                $pourcent=" {$elem["heures_hebdo"]}";
                            }
              
                            $heuresHebdoTitle="Quota hebdomadaire = ".heure4($heuresHebdo, true)." - ".heure4($heuresAbsences, true)." (Absences{$pourcent})";
                            $heuresHebdo=$heuresHebdo-$heuresAbsences;
                            if ($heuresHebdo<0) {
                                $heuresHebdo=0;
                            }
                        }
                    } else {
                        $heuresHebdoTitle="Quota hebdomadaire : Erreur de calcul des heures d'absences";
                        $heuresHebdo="Erreur";
                    }
                }
        
                if (is_numeric($heuresHebdo)) {
                    $heuresHebdo = round($heuresHebdo, 2);
                }

                if (!$config['ClasseParService']) {
                    if ($elem['id']==2) {		// on retire l'utilisateur "tout le monde"
                        continue;
                    }
                }
        
                $title = $elem['nom'] . ' ' . $elem['prenom'];
                $title = in_array($elem['id'], $absences_non_validees) ? ' Absence ou congé non validé' : $title;

                $elem['name_title'] = $title;
        
                $nom = $elem['nom'];
                if ($elem['prenom']) {
                    $nom.=" ".mb_substr($elem['prenom'], 0, 1).".";
                }

                // Si sans repas, on ajoute (SR) à l'affichage
                $elem['no_lunch'] = 0;
                if ($sansRepas === true or in_array($elem['id'], $sansRepas)) {
                    $elem['no_lunch'] = 1;
                }

                // Déjà placés
                $elem['placed'] = 0;
                if ($config['Planning-dejaPlace']) {
                    if (in_array($elem['id'], $deja)) {	// Déjà placé pour ce poste
                        $elem['placed'] = 1;
                    }
                }

                // Vérifie si l'agent fera 2 plages de service public de suite
                $elem['two_sr'] = 0;
                if ($config['Alerte2SP']) {
                    if (in_array($elem['id'], $deuxSP)) {
                        $elem['two_sr'] = 1;
                    }
                }

                $elem['journey'] = 0;
                if (in_array($elem['id'], $journey)) {
                    $elem['journey'] = 1;
                }

                $elem['time_limit'] = 0;
                if (in_array($elem['id'], $absences_journey)) {
                    $elem['time_limit'] = 1;
                }

                // Motifs d'indisponibilité
                $elem['exclusion'] = array();
                if (array_key_exists($elem['id'], $motifExclusion)) {
                    $elem['exclusion'] = $motifExclusion[$elem['id']];
                }

                // Affihage des heures faites ce jour et cette semaine + les heures de la cellule
                if ($GLOBALS['config']['Planning-Heures']) {
                    $hres_jour = isset($heures[$elem['id']]['jour']) ? $heures[$elem['id']]['jour'] : 0;
                    $hres_jour += $hres_cellule;
                    $hres_jour = round($hres_jour, 2);
                    $hres_sem = isset($heures[$elem['id']]['semaine']) ? $heures[$elem['id']]['semaine'] : 0;
                    $hres_sem += $hres_cellule;
                    $hres_sem = round($hres_sem, 2);
          
                    // affihage des heures faites les 4 dernières semaines + les heures de la cellule
                    $hres_4sem=null;
                    if ($config['hres4semaines']) {
                        $hres_4sem = isset($heures[$elem['id']]['4semaines']) ? $heures[$elem['id']]['4semaines'] : 0;
                        $hres_4sem += $hres_cellule;
                        $hres_4sem = round($hres_4sem, 2);
                        $hres_4sem = heure4($hres_4sem, true);
                    }

                    $elem['times'] = array(
                        'day' => heure4($hres_jour, true),
                        'week' => heure4($hres_sem, true),
                        'quota' => heure4($heuresHebdo, true),
                        'quota_title' => $heuresHebdoTitle,
                        'times_four_weeks' => $hres_4sem
                    );

                    // Si absence non validée : affichage en rouge
                    if (in_array($elem['id'], $absences_non_validees)) {
                        $elem['color'] = 'red';
                    } elseif ($hres_jour>7) {			// plus de 7h:jour : rouge
                        $elem['color'] = 'red';
                    } elseif ( (floatval($heuresHebdo) - floatval($hres_sem)) <= 0.5 and (floatval($hres_sem) - floatval($heuresHebdo)) <= 0.5 ) {  // 0,5 du quota hebdo : vert
                        $elem['color'] = 'green';
                    } elseif ($hres_sem>$heuresHebdo) {			// plus du quota hebdo : rouge
                        $elem['color'] = 'red';
                    }
                }

                // Classe en fonction du statut et du service
                $class_tmp=array();
                if ($elem['statut']) {
                    $class_tmp[]="statut_".strtolower(removeAccents(str_replace(" ", "_", $elem['statut'])));
                }
                if ($elem['service']) {
                    $class_tmp[]="service_".strtolower(removeAccents(str_replace(" ", "_", $elem['service'])));
                }
                $classe=empty($class_tmp)?null:implode(" ", $class_tmp);

                $elem['display'] = $display;
                $elem['class'] = $classe;
                $elem['class_tr_list'] = $classTrListe;
                $elem['group_hide'] = $groupe_hide ? $groupe_hide : '';
                $elem['name'] = $nom;

                $menudiv['agents'][] = $elem;
            }
        }
        $this->menudiv=$menudiv;
    }


    /**
    * @function notifications
    * @param string $this->date , date au format YYYY-MM-DD
    * Envoie des notifications en cas de validation ou changement de planning aux agents concernés
    */
    public function notifications()
    {
        $version="ajax";
        require_once "../../personnel/class.personnel.php";
        require_once "../../postes/class.postes.php";
        $config=$GLOBALS['config'];
    
        // Liste des agents actifs
        $entityManager = $GLOBALS['entityManager'];
        $queryBuilder = $entityManager->createQueryBuilder();
        $agents = $queryBuilder->select(array('a'))
            ->from(Agent::class, 'a')
            ->where('a.supprime = :deleted')
            ->setParameter('deleted', '0')
            ->indexBy('a', 'a.id')
            ->getQuery()
            ->getResult();

        // Listes des postes
        $p=new postes();
        $p->fetch();
        $postes=$p->elements;

        // Get teleworking reasons
        $teleworking_reasons = $entityManager->getRepository(AbsenceReason::class)
            ->getRemoteWorkingDescriptions();
    
        if (!empty($teleworking_reasons)) {
            $db = new \db();
            foreach ($teleworking_reasons as &$elem) {
                $elem = $db->escapeString($elem);
            }
            $teleworking_reasons = implode("','", $teleworking_reasons);
        }

        // Recherche des informations dans la table pl_poste pour la date $this->date
        $date=$this->date;
        $site=$this->site;
    
        $this->fetch();
    
        $tab=array();
    
        foreach ($this->elements as $elem) {
            // Si l'id concerne un agent qui a été supprimé, on l'ignore
            $id=$elem['perso_id'];
            if (!array_key_exists($id, $agents)) {
                continue;
            }

            $agent = $agents[$id];
            $start = $elem['date'] . ' ' . $elem["debut"];
            $end = $elem['date'] . ' ' . $elem["fin"];

            // Looking for absences.
            $filter = '';
            $position = isset($postes[$elem['poste']]) ? $postes[$elem['poste']] : null;
            if ($position && $position['teleworking'] == 1) {
                $teleworking_exception = (!empty($teleworking_reasons))
                    ? "AND `motif` NOT IN ('$teleworking_reasons')"
                    : null;

                $filter .= " $teleworking_exception";
            }

            // Overwrite $elem['absent'] = 1 if absences are found in tables absences and conges.
            // NB: $elem['absent'] is already set to 0 or 1 from table pl_poste (1 if manually striped out).
            $db = new \db();
            $db->select('absences', '*', "`debut`<'$end' AND `fin`>'$start' AND `valide` > 0 AND `perso_id`='$id' $filter ");

            if ($db->result) {
                if ($config['Absences-Exclusion'] == 0) {
                    $elem['absent'] = 1;
                } elseif ($config['Absences-Exclusion'] == 1) {
                    foreach ($db->result as $abs) {
                        if ($abs['valide'] != 99999) {
                            $elem['absent'] = 1;
                            break;
                        }
                    }
                }
            }

            // Looking for holidays.
            if ($agent->isOnVacationOn($start, $end)) {
                $elem["absent"] = 1;
            }

            // Création d'un tableau par agent, avec nom, prénom et email
            if (!isset($tab[$id])) {
                $tab[$id]=array("nom"=>$agent->nom(), "prenom"=>$agent->prenom(), "mail"=>$agent->mail(), "planning"=>array());
            }
            // Complète le tableau avec les postes, les sites, horaires et marquage "absent"
            $tab[$id]["planning"][]=array("debut"=> $elem["debut"], "fin"=> $elem["fin"], "absent"=> $elem["absent"], "site"=> $site, "poste"=> $elem['poste']);
        }
    
        // $perso_ids = agents qui recevront une notifications
        $perso_ids=array();

        // Recherche dans la table pl_notifications si des notifications ont déjà été envoyées (précédentes validations)
        $db=new db();
        $db->select2("pl_notifications", "*", array("date"=>$date, "site"=>$site));
    
        // Si non, envoi d'un mail intitulé "planning validé" aux agents concernés par le planning
        // et enregistre les infos dans la table pl_notifications
        if (!$db->result) {
            $notificationType="nouveauPlanning";

            // Enregistrement des infos dans la table BDD
            $insert=array("date"=>$date, "site"=>$site, "data"=>json_encode((array)$tab));
            $db2=new db();
            $db2->CSRFToken = $this->CSRFToken;
            $db2->insert("pl_notifications", $insert);

            // Enregistre les agents qui doivent être notifiés
            $perso_ids=array_keys($tab);
        }
        // Si oui, envoi d'un mail intitulé "planning modifié" aux agents concernés par une modification
        // et met à jour les infos dans la table pl_notifications
        else {
            $notificationType="planningModifie";

            // Lecture des infos de la base de données, comparaison avec les nouvelles données
            // Lecture des infos de la base de données

            $data=$db->result[0]["data"];
            $data=html_entity_decode($data, ENT_QUOTES|ENT_IGNORE, 'UTF-8');
            $data=json_decode($data, true);

            $oldData=array();
            foreach ($data as $key => $value) {
                $oldData[$key]=(array) $value;
                foreach ($oldData[$key]["planning"] as $k => $v) {
                    $oldData[$key]["planning"][$k]=(array) $v;
                }
            }

            // Recherche des différences
            // Ajouts, modifications
            // Pour chaque agent présent dans le nouveau tableau
            foreach ($tab as $key => $value) {
                foreach ($value["planning"] as $k => $v) {
                    if (!isset($oldData[$key])
            or (!isset($oldData[$key]["planning"][$k]))
            or ($v != $oldData[$key]["planning"][$k])) {
                        $perso_ids[]=$key;
                        continue 2;
                    }
                }
            }

            // Suppressions
            // Pour chaque agent présent dans l'ancien tableau
            foreach ($oldData as $key => $value) {
                foreach ($value["planning"] as $k => $v) {
                    if (!isset($tab[$key])
            or (!isset($tab[$key]["planning"][$k]))
            or ($v != $tab[$key]["planning"][$k])) {
                        if (!in_array($key, $perso_ids)) {
                            $perso_ids[]=$key;
                        }
                        continue 2;
                    }
                }
            }

            // Modification des infos dans la BDD
            $update=array("data"=>json_encode((array)$tab));
            $db=new db();
            $db->CSRFToken = $this->CSRFToken;
            $db->update("pl_notifications", $update, array("date"=>$date, "site"=>$site));
        }

        /*
        $tab[$perso_id] = Array(nom, prenom, mail, planning => Array(
          [0] => Array(debut, fin, absent, site, poste),
          [1] => Array(debut, fin, absent, site, poste), ...))
        */

        // Envoi du mail

        // Send notifications of initial validation or modification depending on the chosen parameters
        // Settings values : -2 = disabled ; -1 = do not check the date ; 0+ = for dates between today and today + value
        $setting = $notificationType == 'nouveauPlanning' ? $config['Planning-InitialNotification'] : $config['Planning-ChangeNotification'];

        if ($setting == '-2') {
            return;
        }

        if ($setting > '-1') {
            $datePlanning = new DateTimeImmutable($date);
            $now = new DateTimeImmutable('midnight');
            $diff = intval($now->diff($datePlanning)->format('%R%a'));

            if ($diff < 0 or $diff > $setting) {
                return;
            }
        }

        $sujet=$notificationType=="nouveauPlanning"?"Validation du planning du ".dateFr($date):"Modification du planning du ".dateFr($date);

        // Tous les agents qui doivent être notifiés.
        foreach ($perso_ids as $elem) {
            // Création du message avec date et nom de l'agent
            $agent = isset($tab[$elem]) ? $tab[$elem]['prenom'].' '.$tab[$elem]['nom'] : $oldData[$elem]['prenom'].' '.$oldData[$elem]['nom'];
            $location = $GLOBALS['config']['Multisites-nombre'] > 1 ? '<br/>Site : <strong>' . $GLOBALS['config']["Multisites-site{$site}"] . '</strong>' : null;

            $message=$notificationType=="nouveauPlanning"?"Validation du planning":"Modification du planning";
            $message .= "<br/><br/>Agent : <strong>$agent</strong>";
            $message .= "<br/>Date : <strong>".dateFr($date)."</strong>";
            $message .= $location;
      
            // S'il y a des éléments, on ajoute la liste des postes occupés avec les horaires
            if (isset($tab[$elem])) {
                $lines=array();
                $message.="<ul>";

                foreach ($tab[$elem]["planning"] as $e) {
                    // On marque en gras les modifications
                    $exists=true;
                    if ($notificationType=="planningModifie") {
                        $exists=false;
                        if (isset($oldData[$elem])) {
                            foreach ($oldData[$elem]["planning"] as $o) {
                                if ($e==$o) {
                                    $exists=true;
                                    continue;
                                }
                            }
                        }
                    }
                    $bold=$exists?null:"font-weight:bold;";

                    $striped = $e['absent'] == '1' ? 'text-decoration:line-through; color:red;' : null;

                    // Affichage de la ligne avec horaires et poste
                    $poste = html_entity_decode($postes[$e['poste']]['nom'], ENT_QUOTES|ENT_IGNORE, 'UTF-8');
                    $line="<li><span style='$bold $striped'>".heure2($e['debut'])." - ".heure2($e['fin'])." : $poste";
                    $line.="</span>";

                    // On ajoute "(supprimé)" et une étoile en cas de modif car certains webmail suppriment les balises et le style "bold", etc.
                    if ($striped) {
                        $line .= " (supprimé)";
                    }
                    if ($bold) {
                        $line.="<sup style='font-weight:bold;'>*</sup>";
                    }
                    $line.="</li>";
                    $lines[]=array($e['debut'],$line);
                }

                // On affiche les suppressions
                if (isset($oldData[$elem])) {
                    foreach ($oldData[$elem]["planning"] as $e) {
                        $exists=false;
                        if (isset($tab[$elem])) {
                            foreach ($tab[$elem]["planning"] as $e2) {
                                if ($e['debut']==$e2['debut']) {
                                    $exists=true;
                                    continue;
                                }
                            }
                        }
                        if (!$exists) {
                            // Affichage de l'ancienne ligne avec horaires et poste
                            $poste = html_entity_decode($postes[$e['poste']]['nom'], ENT_QUOTES|ENT_IGNORE, 'UTF-8');
                            $line="<li><span style='font-weight:bold; text-decoration:line-through; color:red;'>".heure2($e['debut'])." - ".heure2($e['fin'])." : $poste";
                            $line.="</span>";
                            $line.=" (supprim&eacute;)";
                            $line.="<sup style='font-weight:bold;'>*</sup>";
                            $lines[]=array($e['debut'],$line);
                        }
                    }
                }

                sort($lines);
                foreach ($lines as $line) {
                    $message.=$line[1];
                }
                $message.="</ul>";

                // On ajoute le lien vers le planning
                $url = $config['URL'] . '/' . $date;
                $message.="Lien vers le planning du ".dateFr($date)." : $url";

                // Envoi du mail
                $m=new CJMail();
                $m->subject=$sujet;
                $m->message=$message;
                $m->to=$tab[$elem]['mail'];
                $m->send();

            // S'il n'y a pas d'éléments, on écrit "Vous n'êtes plus dans le planning ..."
            } else {
                // On ajoute le lien vers le planning
                $url = $config['URL'] . '/' . $date;
                $message.="<br/><br/>Vous n&apos;&ecirc;tes plus dans le planning du ".dateFr($date);
                $message.="<br/><br/>Lien vers le planning du ".dateFr($date)." : $url";

                // Envoi du mail
                $m=new CJMail();
                $m->subject=$sujet;
                $m->message=$message;
                $m->to=$oldData[$elem]['mail'];
                $m->send();
            }
        }
    }
  
    // Notes
    // Récupère les notes (en bas des plannings)
    public function getNotes()
    {
        $date = $this->date;

        $this->notes = null;

        $dates = is_array($date) ? $date : array($date);
        foreach ($dates as $d) {
            $this->comments[$d][$this->site] = array(
                'notes' =>null,
                'textarea' => null,
                'validation' => null,
                'display' => false,
                'deleted' => null,
            );
        }

        if (is_array($date)) {
            $dates = implode(',', $date);
            $db=new db();
            $db->select2('pl_notes', '*', array('date' => "IN$dates", 'site' => $this->site), 'ORDER BY `timestamp` ASC');
        } else {
            $db=new db();
            $db->select2('pl_notes', '*', array('date' => $date, 'site' => $this->site), 'ORDER BY `timestamp` DESC LIMIT 1');
        }

        if ($db->result) {
            foreach ($db->result as $elem) {
                $notes = $elem['text'];
                $notes = str_replace(array("&lt;br/&gt;","#br#"), "<br/>", $notes);
                $notesTextarea = str_replace("<br/>", "\n", $notes);
    
                if ($elem['perso_id'] and $elem['timestamp']) {
                    $validation = nom($elem['perso_id']).", ".dateFr($elem['timestamp'], true);
                } else {
                    $validation = null;
                }

                $this->notes = $notes;
                $this->notesTextarea = $notesTextarea;
                $this->validation = $validation;

                $this->comments[$elem['date']][$elem['site']] = array(
                    'notes' => $notes,
                    'textarea' => $notesTextarea,
                    'validation' => $validation,
                    'display' => trim(strval($notes)) ? true : false,
                    'deleted' => ($validation and !trim(strval($notes))) ? 'Suppression du commentaire : ' : null,
               );
            }
        }
    }

    public function update_cell_add_agents($date, $debut, $fin, $poste, $site, $perso_id, $login_id, $CSRFToken)
    {
        $insert = array(
            "date" => $date,
            "debut" => $debut,
            "fin" => $fin,
            "poste" => $poste,
            "site" => $site,
            "perso_id" => $perso_id,
            "chgt_login" => $login_id,
            "chgt_time" => date("Y-m-d H:i:s")
        );

        $db = new db();
        $db->CSRFToken = $CSRFToken;
        $db->insert("pl_poste", $insert);
    }

    // Insertion, mise à jour des notes
    public function updateNotes()
    {
        $date=$this->date;
        $site=$this->site;
        $text=$this->notes;

        // Vérifie s'il y a eu des changements depuis le dernier enregistrement
        $this->getNotes();
        $previousNotes=str_replace("<br/>", "#br#", $this->notes);
        // Si non, on enregistre la nouvelle note
        if (strcmp($previousNotes, $text)!=0) {
            $db=new db();
            $db->CSRFToken = $this->CSRFToken;
            $db->insert("pl_notes", array("date"=>$date,"site"=>$site,"text"=>$text,"perso_id"=>$_SESSION['login_id']));
        }
    }

    /**
    * Fonction sansRepas
    * Retourne une tableau contenant les agents placés en continu entre les heures de début et de fin définies dans la config. pour les sans repas
    * Ou retourne true si la plage intérrogée couvre complétement la préiode définie dans la config.
    * @param string $date
    * @param string $debut
    * @param string $fin
    * @return array / true
    */
    public function sansRepas($date, $debut, $fin, $poste = null)
    {
        if ($GLOBALS['config']['Planning-sansRepas']==0) {
            return array();
        }

        $positions = $GLOBALS['entityManager']->getRepository(Position::class);

        $lunch_positions = array();
        foreach ($positions->findBy(['lunch' => true]) as $elem) {
            $lunch_positions[] = $elem->id(); 
        }

        $lunch_positions = implode(',', $lunch_positions);
       
        if (isset($poste) and $positions->find($poste)->lunch()) {
            return array();
        }

        $sr_debut=$GLOBALS['config']['Planning-SR-debut'];
        $sr_fin=$GLOBALS['config']['Planning-SR-fin'];
    
        // Si la plage couvre complétement la période de sans repas, on retourne true, tous les agents seront marqués en sans repas
        if ($debut <= $sr_debut and $fin >= $sr_fin) {
            return true;
        }
    
        // Par défaut, personne en sans repas => $sr = tableau vide
        $sr=array();
    
        // Si la plage interrogée est dans ou à cheval sur la période de sans repas
        if ($debut<$sr_fin and $fin>$sr_debut) {

      // Recherche dans la base de données des autres plages concernées
            $db=new db();

            if (!empty($lunch_positions)) {
                $db->select("pl_poste", "*", "date = '$date' AND debut < '$sr_fin' AND fin > '$sr_debut' AND poste NOT IN ($lunch_positions)", "ORDER BY debut,fin");
            } else {
                $db->select2("pl_poste", "*", array("date"=>$date, "debut"=>"<$sr_fin", "fin"=>">$sr_debut"), "ORDER BY debut,fin");
            }

            if ($db->result) {
                $result = array();
                // On classe les résultats par agent
                foreach ($db->result as $elem) {
                    if ($elem['perso_id'] == 0) {
                        continue;
                    }
                    // On commence par ajouter la plage interrogée à chaque agent
                    if (!array_key_exists($elem['perso_id'], $result)) {
                        $result[$elem['perso_id']] = array(array('debut'=>$debut, 'fin'=>$fin));
                    }
                    // Et on ajoute les plages déjà renseignées pour chaque agent
                    $result[$elem['perso_id']][]=array('debut'=>$elem['debut'], 'fin'=>$elem['fin']);
                }
        
                // Tableau result contient pour chaque agent les plages de la base de données + la plage interrogée
                // Tri du tableau de chaque agents
                foreach ($result as $key => $value) {
                    usort($result[$key], "cmp_debut_fin");
                }
        
                // Si le plus petit début et inférieur ou égal au début de la période SR et la plus grande fin supérieure ou égale à la fin de la période SR
                // = Possibilité que la période soit complète, on met SR=1
                foreach ($result as $key => $value) {
                    $sansRepas=false;
                    if ($value[0]["debut"]<=$sr_debut and $value[count($value)-1]["fin"]>=$sr_fin) {
                        $sansRepas=true;
                        // On consulte toutes les plages à la recherche d'une interruption. Si interruption, sr=0 et on quitte la boucle
                        $last_end=$value[0]['fin'];
                        for ($i=1;$i<count($value);$i++) {
                            if ($value[$i]['debut']>$last_end) {
                                $sansRepas=false;
                                continue 2;
                            }
                            $last_end=$value[$i]['fin'];
                        }
                    }
                    if ($sansRepas) {
                        $sr[]=$key;
                    }
                }
            }
        }
        return $sr;
    }
}
