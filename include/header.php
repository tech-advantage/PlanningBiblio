<?php
/**
Planning Biblio, Version 2.7
Licence GNU/GPL (version 2 et au dela)
Voir les fichiers README.md et LICENSE
@copyright 2011-2018 Jérôme Combes

Fichier : include/header.php
Création : mai 2011
Dernière modification : 19 juillet 2017
@author Jérôme Combes <jerome@planningbiblio.fr>

Description :
Affcihe l'entête HTML
Page notamment appelée par les fichiers index.php, admin/index.php
*/

// Contrôle si ce script est appelé directement, dans ce cas, affiche Accès Refusé et quitte
if(__FILE__ == $_SERVER['SCRIPT_FILENAME']){
  include_once "accessDenied.php";
  exit;
}

$theme=$config['Affichage-theme']?$config['Affichage-theme']:"default";
$themeJQuery=$config['Affichage-theme']?$config['Affichage-theme']:"default";
if(!file_exists("themes/$theme/jquery-ui.min.css")){
  $themeJQuery="default";
}
if(!file_exists("themes/$theme/$theme.css")){
  $theme="default";
}

$favicon = null;
if(!file_exists("themes/$theme/favicon.png")){
  $favicon = "<link rel='icon' type='image/png' href='themes/$theme/images/favicon.png' />\n";
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
<title>Planning</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<script type='text/JavaScript' src='vendor/jquery-1.11.1.min.js'></script>
<script type='text/JavaScript' src='vendor/jquery-ui-1.11.2/jquery-ui.js'></script>
<script type='text/JavaScript' src='vendor/carhartl-jquery-cookie-3caf209/jquery.cookie.js'></script>
<script type='text/JavaScript' src='vendor/DataTables-1.10.4/media/js/jquery.dataTables.min.js'></script>
<script type='text/JavaScript' src='vendor/DataTables-1.10.4/extensions/FixedColumns/js/dataTables.fixedColumns.min.js'></script>
<script type='text/JavaScript' src='vendor/DataTables-1.10.4/extensions/TableTools/js/dataTables.tableTools.min.js'></script>
<script type='text/JavaScript' src='vendor/dataTables.jqueryui.js'></script>
<script type='text/JavaScript' src='vendor/CJScript.js?version=<?php echo $version; ?>'></script>
<script type='text/JavaScript' src='js/datePickerFr.js?version=<?php echo $version; ?>'></script>
<script type='text/JavaScript' src='js/dataTables.sort.js?version=<?php echo $version; ?>'></script>
<script type='text/JavaScript' src='js/script.js?version=<?php echo $version; ?>'></script>
<?php
getJSFiles($page,$version);
?>

<link rel='StyleSheet' href='vendor/DataTables-1.10.4/media/css/jquery.dataTables_themeroller.css' type='text/css' media='all'/>
<link rel='StyleSheet' href='vendor/DataTables-1.10.4/extensions/TableTools/css/dataTables.tableTools.min.css' type='text/css' media='all'/>
<link rel='StyleSheet' href='themes/<?php echo $themeJQuery; ?>/jquery-ui.min.css' type='text/css' media='all'/>
<link rel='StyleSheet' href='themes/default/default.css?version=<?php echo $version; ?>' type='text/css' media='all'/>
<link rel='StyleSheet' href='themes/default/print.css?version=<?php echo $version; ?>' type='text/css' media='print'/>
<?php
if($theme!="default"){
  echo "<link rel='StyleSheet' href='themes/{$theme}/{$theme}.css?version=$version' type='text/css' media='all'/>\n";
}
echo $favicon;
?>
</head>

<body>

<?php
// Affichage des messages d'erreur ou de confirmation venant de la page précedente
$msg=filter_input(INPUT_GET,"msg", FILTER_SANITIZE_STRING);
$msgType=filter_input(INPUT_GET,"msgType", FILTER_SANITIZE_STRING);
if($msg){
  echo "<script type='text/JavaScript'>CJInfo('$msg','$msgType');</script>\n";
}

$msg2=filter_input(INPUT_GET,"msg2", FILTER_SANITIZE_STRING);
$msg2Type=filter_input(INPUT_GET,"msg2Type", FILTER_SANITIZE_STRING);
if($msg2){
  echo "<script type='text/JavaScript'>CJInfo('$msg2','$msg2Type',82,15000);</script>\n";
}
?>

<div id='opac' style='display:none'></div>
<div style='position:relative;top:30px;' class='noprint'>
</div>