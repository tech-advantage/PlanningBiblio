<?php
/*
Planning Biblio, Version 1.5.8
Licence GNU/GPL (version 2 et au dela)
Voir les fichiers README.txt et COPYING.txt
Copyright (C) 2011-2013 - Jérôme Combes

Fichier : statistiques/statut.php
Création : 13 septembre 2013
Dernière modification : 16 septembre 2013
Auteur : Jérôme Combes, jerome@planningbilbio.fr

Description :
Affiche les statistiques par statut

Page appelée par le fichier index.php, accessible par le menu statistiques / Par statut
*/

require_once "class.statistiques.php";

echo "<h3>Statistiques par statut</h3>\n";

// Initialisation des variables :
$joursParSemaine=$config['Dimanche']?7:6;
$postes=null;
$selected=null;
$heure=null;
$statuts_tab=null;
$exists_h19=false;
$exists_h20=false;
$exists_JF=false;

include "include/horaires.php";
//		--------------		Initialisation  des variables 'debut','fin' et 'statut'		-------------------
if(!array_key_exists('stat_statut_statuts',$_SESSION['oups'])){
  $_SESSION['oups']['stat_statut_statuts']=null;
}
if(!array_key_exists('stat_debut',$_SESSION['oups'])){
  $_SESSION['oups']['stat_debut']=null;
  $_SESSION['oups']['stat_fin']=null;
}
$debut=isset($_GET['debut'])?$_GET['debut']:$_SESSION['oups']['stat_debut'];
$fin=isset($_GET['fin'])?$_GET['fin']:$_SESSION['oups']['stat_fin'];
$statuts=isset($_GET['statuts'])?$_GET['statuts']:$_SESSION['oups']['stat_statut_statuts'];
if(!$debut){
  $debut=date("Y")."-01-01";
}
$_SESSION['oups']['stat_debut']=$debut;
if(!$fin){
  $fin=date("Y-m-d");
}
$_SESSION['oups']['stat_fin']=$fin;
$_SESSION['oups']['stat_statut_statuts']=$statuts;

// Filtre les sites
if(!array_key_exists('stat_statut_sites',$_SESSION)){
  $_SESSION['stat_statut_sites']=null;
}
$selectedSites=isset($_GET['selectedSites'])?$_GET['selectedSites']:$_SESSION['stat_statut_sites'];
if($config['Multisites-nombre']>1 and !$selectedSites){
  $selectedSites=array();
  for($i=1;$i<=$config['Multisites-nombre'];$i++){
    $selectedSites[]=$i;
  }
}
$_SESSION['stat_statut_sites']=$selectedSites;

// Filtre les sites dans les requêtes SQL
if($config['Multisites-nombre']>1 and is_array($selectedSites)){
  $reqSites="AND `{$dbprefix}pl_poste`.`site` IN (0,".join(",",$selectedSites).")";
}
else{
  $reqSites=null;
}

$tab=array();

//		--------------		Récupération de la liste des statuts pour le menu déroulant		------------------------
$db=new db();
$db->select("select_statuts");
$statuts_list=$db->result;

if(is_array($statuts) and $statuts[0]){
  //	Recherche du nombre de jours concernés
  $db=new db();
  $db->select("pl_poste","date","`date` BETWEEN '$debut' AND '$fin' $reqSites","GROUP BY `date`");
  $nbJours=$db->nb;
  $nbSemaines=$nbJours>0?$nbJours/$joursParSemaine:1;


  // Recherche des statuts de chaque agent
  $db=new db();
  $db->select("personnel","id,statut");
  foreach($db->result as $elem){
    $statutId=null;
    foreach($statuts_list as $stat){
      if($stat['valeur']==$elem['statut']){
	$statutId=$stat['id'];
	continue;
      }
    }
    $agents[$elem['id']]=array("id"=>$elem['id'],"statut"=>$elem['statut'],"statut_id"=>$statutId);
  }

  //	Recherche des infos dans pl_poste et postes pour tous les statuts sélectionnés
  //	On stock le tout dans le tableau $resultat

  $db=new db();
  $req="SELECT `{$dbprefix}pl_poste`.`debut` as `debut`, `{$dbprefix}pl_poste`.`fin` as `fin`, 
    `{$dbprefix}pl_poste`.`date` as `date`, `{$dbprefix}pl_poste`.`perso_id` as `perso_id`, 
    `{$dbprefix}pl_poste`.`poste` as `poste`, `{$dbprefix}pl_poste`.`absent` as `absent`, 
    `{$dbprefix}postes`.`nom` as `poste_nom`, `{$dbprefix}postes`.`etage` as `etage`,
    `{$dbprefix}pl_poste`.`site` as `site` 
    FROM `{$dbprefix}pl_poste` 
    INNER JOIN `{$dbprefix}postes` ON `{$dbprefix}pl_poste`.`poste`=`{$dbprefix}postes`.`id` 
    WHERE `{$dbprefix}pl_poste`.`date`>='$debut' AND `{$dbprefix}pl_poste`.`date`<='$fin' 
    AND `{$dbprefix}pl_poste`.`supprime`<>'1' AND `{$dbprefix}postes`.`statistiques`='1' $reqSites 
    ORDER BY `poste_nom`,`etage`;";
  $db->query($req);
  $resultat=$db->result;

  // Ajoute le statut pour chaque agents dans le tableau resultat
  for($i=0;$i<count($resultat);$i++){
    $resultat[$i]['statut']=$agents[$resultat[$i]['perso_id']]['statut'];
    $resultat[$i]['statut_id']=$agents[$resultat[$i]['perso_id']]['statut_id'];
  }

  //	Recherche des infos dans le tableau $resultat (issu de pl_poste et postes)
  //	pour chaque statut sélectionné

  foreach($statuts as $statut){
    if(array_key_exists($statut,$tab)){
      $heures=$tab[$statut][2];
      $total_absences=$tab[$statut][5];
      $samedi=$tab[$statut][3];
      $dimanche=$tab[$statut][6];
      $h19=$tab[$statut][7];
      $h20=$tab[$statut][8];
      $absences=$tab[$statut][4];
      $feries=$tab[$statut][9];
      $sites=$tab[$service]["sites"];
    }
    else{
      $heures=0;
      $total_absences=0;
      $samedi=array();
      $dimanche=array();
      $absences=array();
      $h19=array();
      $h20=array();
      $feries=array();
      for($i=1;$i<=$config['Multisites-nombre'];$i++){
	$sites[$i]=0;
      }
    }
    $postes=Array();
    if(is_array($resultat)){
      foreach($resultat as $elem){
	if($statut==$elem['statut_id']){
	  if($elem['absent']!="1"){		// on compte les heures et les samedis pour lesquels l'agent n'est pas absent
	    // on créé un tableau par poste avec son nom, étage et la somme des heures faites par statut
	    if(!array_key_exists($elem['poste'],$postes)){
	      $postes[$elem['poste']]=Array($elem['poste'],$elem['poste_nom'],$elem['etage'],0,"site"=>$elem['site']);
	    }
	    $postes[$elem['poste']][3]+=diff_heures($elem['debut'],$elem['fin'],"decimal");
	    // On compte les heures de chaque site
	    if($config['Multisites-nombre']>1){
	      $sites[$elem['site']]+=diff_heures($elem['debut'],$elem['fin'],"decimal");
	    }
	    // On compte toutes les heures (globales)
	    $heures+=diff_heures($elem['debut'],$elem['fin'],"decimal");
	    $d=new datePl($elem['date']);
	    if($d->sam=="samedi"){	// tableau des samedis
	      if(!array_key_exists($elem['date'],$samedi)){ // on stock les dates et la somme des heures faites par date
		$samedi[$elem['date']][0]=$elem['date'];
		$samedi[$elem['date']][1]=0;
	      }
	      $samedi[$elem['date']][1]+=diff_heures($elem['debut'],$elem['fin'],"decimal");
	    }
	    if($d->position==0){		// tableau des dimanches
	      if(!array_key_exists($elem['date'],$dimanche)){ 	// on stock les dates et la somme des heures faites par date
		$dimanche[$elem['date']][0]=$elem['date'];
		$dimanche[$elem['date']][1]=0;
	      }
	      $dimanche[$elem['date']][1]+=diff_heures($elem['debut'],$elem['fin'],"decimal");
	    }
	    if(jour_ferie($elem['date'])){
	      if(!array_key_exists($elem['date'],$feries)){
		$feries[$elem['date']][0]=$elem['date'];
		$feries[$elem['date']][1]=0;
	      }
	      $feries[$elem['date']][1]+=diff_heures($elem['debut'],$elem['fin'],"decimal");
	      $exists_JF=true;
	    }

	    //	On compte les 19-20
	    if($elem['debut']=="19:00:00"){
	      $h19[]=$elem['date'];
	      $exists_h19=true;
	    }
	    //	On compte les 20-22
	    if($elem['debut']=="20:00:00"){
	      $h20[]=$elem['date'];
	      $exists_h20=true;;
	    }
	  }
	  else{				// On compte les absences
	    if(!array_key_exists($elem['date'],$absences)){
	      $absences[$elem['date']][0]=$elem['date'];
	      $absences[$elem['date']][1]=0;
	    }
	    $absences[$elem['date']][1]+=diff_heures($elem['debut'],$elem['fin'],"decimal");
	    $total_absences+=diff_heures($elem['debut'],$elem['fin'],"decimal");
	    // $absences[]=array($elem['date'],diff_heures($elem['debut'],$elem['fin'],"decimal"));
	    
						    // A CONTINUER  
	  }
	  // On met dans tab tous les éléments (infos postes + statuts + heures)
	  $tab[$statut]=array($elem['statut'],$postes,$heures,$samedi,$absences,$total_absences,$dimanche,$h19,$h20,$feries,"sites"=>$sites);
	}
      }
    }
  }
}
// passage en session du tableau pour le fichier export.php
$_SESSION['stat_tab']=$tab;

//		--------------		Affichage en 2 partie : formulaire à gauche, résultat à droite
echo "<table><tr style='vertical-align:top;'><td style='width:300px;'>\n";
//		--------------		Affichage du formulaire permettant de sélectionner les dates et les agents		-------------
echo "<form name='form' action='index.php' method='get'>\n";
echo "<input type='hidden' name='page' value='statistiques/statut.php' />\n";
echo "<table>\n";
echo "<tr><td>Début : </td>\n";
echo "<td><input type='text' name='debut' value='$debut' />&nbsp;<img src='img/calendrier.gif' onclick='calendrier(\"debut\");' alt='calendrier' />\n";
echo "</td></tr>\n";
echo "<tr><td>Fin : </td>\n";
echo "<td><input type='text' name='fin' value='$fin' />&nbsp;<img src='img/calendrier.gif' onclick='calendrier(\"fin\");' alt='calendrier' />\n";
echo "</td></tr>\n";
echo "<tr style='vertical-align:top'><td>Services : </td>\n";

echo "<td><select name='statuts[]' multiple='multiple' size='20' onchange='verif_select(\"statuts\");'>\n";

if(is_array($statuts_list)){
  echo "<option value='Tous'>Tous</option>\n";
  foreach($statuts_list as $elem){
    $selected=in_array($elem['id'],$statuts)?"selected='selected'":null;
    echo "<option value='{$elem['id']}' $selected>{$elem['valeur']}</option>\n";
  }
}
echo "</select></td></tr>\n";

if($config['Multisites-nombre']>1){
  $nbSites=$config['Multisites-nombre'];
  echo "<tr style='vertical-align:top'><td>Sites : </td>\n";
  echo "<td><select name='selectedSites[]' multiple='multiple' size='".($nbSites+1)."' onchange='verif_select(\"selectedSites\");'>\n";
  echo "<option value='Tous'>Tous</option>\n";
  for($i=1;$i<=$nbSites;$i++){
    $selected=in_array($i,$selectedSites)?"selected='selected'":null;
    echo "<option value='$i' $selected>{$config["Multisites-site$i"]}</option>\n";
  }
  echo "</select></td></tr>\n";
}

echo "<tr><td colspan='2' style='text-align:center;'>\n";
echo "<input type='button' value='Effacer' onclick='location.href=\"index.php?page=statistiques/statut.php&amp;debut=&amp;fin=&amp;agents=\"' />\n";
echo "&nbsp;&nbsp;<input type='submit' value='OK' />\n";
echo "</td></tr>\n";
echo "<tr><td colspan='2'><hr/></td></tr>\n";
echo "<tr><td>Exporter </td>\n";
echo "<td><a href='javascript:export_stat(\"statut\",\"csv\");'>CSV</a>&nbsp;&nbsp;\n";
echo "<a href='javascript:export_stat(\"statut\",\"xsl\");'>XLS</a></td></tr>\n";
echo "</table>\n";
echo "</form>\n";

//		--------------------------		2eme partie (2eme colonne)		--------------------------
echo "</td><td>\n";

// 		--------------------------		Affichage du tableau de résultat		--------------------
if($tab){
  echo "<b>Statistiques par statut du ".dateFr($debut)." au ".dateFr($fin)."</b><br/>\n";
  echo $nbJours>1?"$nbJours jours, ":"$nbJours jour, ";
  echo $nbSemaines>1?number_format($nbSemaines,2,',',' ')." semaines":number_format($nbSemaines,2,',',' ')." semaine";
  echo "<table border='1' cellspacing='0' cellpadding='0'>\n";
  echo "<tr class='th'>\n";
  echo "<td style='width:200px; padding-left:8px;'>Services</td>\n";
  echo "<td style='width:280px; padding-left:8px;'>Postes</td>\n";
  echo "<td style='width:120px; padding-left:8px;'>Samedi</td>\n";
  if($config['Dimanche']){
    echo "<td style='width:120px; padding-left:8px;'>Dimanche</td>\n";
  }
  if($exists_JF){
    echo "<td style='width:120px; padding-left:8px;'>J. F&eacute;ri&eacute;s</td>\n";
  }
  if($exists_h19){
    echo "<td style='width:120px; padding-left:8px;'>19-20</td>\n";
  }
  if($exists_h20){
    echo "<td style='width:120px; padding-left:8px;'>20-22</td>\n";
  }
  echo "<td style='width:120px; padding-left:8px;'>Absences</td></tr>\n";
  foreach($tab as $elem){
    $jour=$elem[2]/$nbJours;
    $hebdo=$jour*$joursParSemaine;
    echo "<tr style='vertical-align:top;'>\n";
    //	Affichage du nom des services dans la 1ère colonne
    echo "<td style='padding-left:8px;'>";
    echo "<table><tr><td colspan='2'><b>{$elem[0]}</b></td></tr>\n";
    echo "<tr><td>Total</td>\n";
    echo "<td style='text-align:right;'>".number_format($elem[2],2,',',' ')."</td></tr>\n";
    echo "<tr><td>Moyenne hebdo</td>\n";
    echo "<td style='text-align:right;'>".number_format(round($hebdo,2),2,',',' ')."</td></tr>\n";
    if($config['Multisites-nombre']>1){
      for($i=1;$i<=$config['Multisites-nombre'];$i++){
	if($elem["sites"][$i]){
	  // Calcul des moyennes
	  $jour=$elem["sites"][$i]/$nbJours;
	  $hebdo=$jour*$joursParSemaine;
	  echo "<tr><td colspan='2' style='padding-top:20px;'><u>".$config["Multisites-site{$i}"]."</u></td></tr>";
	  echo "<tr><td>Total</td>";
	  echo "<td style='text-align:right;'>".number_format($elem["sites"][$i],2,',',' ')."</td></tr>";;
	  echo "<tr><td>Moyenne</td>";
	  echo "<td style='text-align:right;'>".number_format($hebdo,2,',',' ')."</td></tr>";
	}
      }
    }
    echo "</table>\n";
    echo "</td>\n";
    //	Affichage du noms des postes et des heures dans la 2eme colonne
    echo "<td style='padding-left:8px;'>";
    echo "<table>\n";
    foreach($elem[1] as $poste){
      $site=null;
      if($poste["site"]>0 and $config['Multisites-nombre']>1){
	$site=$config["Multisites-site{$poste['site']}"]." ";
      }
      $etage=$poste[2]?$poste[2]:null;
      $siteEtage=($site or $etage)?"($site{$etage})":null;
      echo "<tr style='vertical-align:top;'><td>\n";
      echo "<b>{$poste[1]}</b><br/><i>$siteEtage</i>";
      echo "</td><td style='text-align:right;'>\n";
      echo number_format($poste[3],2,',',' ');
      echo "</td></tr>\n";
    }
    echo "</table>\n";
    echo "</td>\n";
    //	Affichage du nombre de samedis travaillés et les heures faites par samedi
    echo "<td style='padding-left:8px;'>";
    $samedi=count($elem[3])>1?"samedis":"samedi";
    echo count($elem[3])." $samedi";		//	nombre de samedi
    echo "<br/>\n";
    sort($elem[3]);				//	tri les samedis par dates croissantes
    foreach($elem[3] as $samedi){			//	Affiche les dates et heures des samedis
      echo dateFr($samedi[0]);			//	date
      echo "&nbsp;:&nbsp;".number_format($samedi[1],2,',',' ')."<br/>";	// heures
    }
    echo "</td>\n";
    if($config['Dimanche']){
      echo "<td style='padding-left:8px;'>";
      $dimanche=count($elem[6])>1?"dimanches":"dimanche";
      echo count($elem[6])." $dimanche";	//	nombre de dimanche
      echo "<br/>\n";
      sort($elem[6]);				//	tri les dimanches par dates croissantes
      foreach($elem[6] as $dimanche){		//	Affiche les dates et heures des dimanches
	echo dateFr($dimanche[0]);		//	date
	echo "&nbsp;:&nbsp;".number_format($dimanche[1],2,',',' ')."<br/>";	//	heures
      }
      echo "</td>\n";
    }

    if($exists_JF){
      echo "<td style='padding-left:8px;'>";					//	Jours feries
      $ferie=count($elem[9])>1?"J. f&eacute;ri&eacute;s":"J. f&eacute;ri&eacute;";
      echo count($elem[9])." $ferie";		//	nombre de dimanche
      echo "<br/>\n";
      sort($elem[9]);				//	tri les jours fériés par dates croissantes
      foreach($elem[9] as $ferie){		// 	Affiche les dates et heures des jours fériés
	echo dateFr($ferie[0]);			//	date
	echo "&nbsp;:&nbsp;".number_format($ferie[1],2,',',' ')."<br/>";	//	heures
      }
      echo "</td>";	
    }

    if($exists_h19){
      echo "<td>\n";				//	Affichage des 19-20
      if(array_key_exists(0,$elem[7])){
	sort($elem[7]);
	echo "Nb 19-20 : ";
	echo count($elem[7]);
	foreach($elem[7] as $h19){
	  echo "<br/>".dateFr($h19);
	}
      }
    echo "</td>\n";
    }
    if($exists_h19){
      echo "<td>\n";				//	Affichage des 20-22
      if(array_key_exists(0,$elem[8])){
	sort($elem[8]);
	echo "Nb 20-22 : ";
	echo count($elem[8]);
	foreach($elem[8] as $h20){
	  echo "<br/>".dateFr($h20);
	}
      }
      echo "</td>\n";
    }
	    
    echo "<td>\n";
    if($elem[5]){				//	Affichage du total d'heures d'absences
      echo "Total : ".number_format($elem[5],2,',',' ')."<br/>";
    }
    sort($elem[4]);				//	tri les absences par dates croissantes
    foreach($elem[4] as $absences){		//	Affiche les dates et heures des absences
      echo dateFr($absences[0]);		//	date
      echo "&nbsp;:&nbsp;".number_format($absences[1],2,',',' ')."<br/>";	// heures
    }
    echo "</td>\n";
    echo "</tr>\n";
  }
  echo "</table>\n";
}
//		----------------------			Fin d'affichage		----------------------------
echo "</td></tr></table>\n";
?>