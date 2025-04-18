<?php
/**
Planning Biblio, Version 2.1
Licence GNU/GPL (version 2 et au dela)
Voir les fichiers README.md et LICENSE
@copyright 2011-2018 Jérôme Combes

Fichier : include/sanitize.php
Création : 7 avril 2015
Dernière modification : 22 janvier 2016
@author Jérôme Combes <jerome@planningbiblio.fr>

Description :
Page contenant les fonctions PHP de nettoyages de variables
Page appelée par le fichier index.php
*/

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

// Contrôle si ce script est appelé directement, dans ce cas, affiche Accès direct et quitte
if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) {
    include_once "accessDenied.php";
    exit;
}

function sanitize_html($input) {
    $htmlSanitizer = new HtmlSanitizer(
        (new HtmlSanitizerConfig())->allowSafeElements()
    );
    return $htmlSanitizer->sanitize($input);
}

function sanitize_array_unsafe($n)
{
    if (is_array($n)) {
        return array_map("sanitize_array_unsafe", $n);
    }
    return filter_var($n, FILTER_UNSAFE_RAW);
}

function sanitize_color($input) {
    if (preg_match_all('/^#(?:[0-9a-fA-F]{3,6})$/', $input, $matches)) {
        return $input;
    }

    return null;
}

function sanitize_dateFr($input)
{
    $reponse_filtre = null;
    // Vérifions si le format est valide
    if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $input, $matches)) {
        // Vérifions si la date existe
        if (checkdate($matches[2], $matches[1], $matches[3])) {
            $reponse_filtre = $input;
        }
    }
    return $reponse_filtre;
}

function sanitize_dateSQL($input)
{
    $reponse_filtre = null;
    // Vérifions si le format est valide
    if (preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $input, $matches)) {
        // Vérifions si la date existe
        if (checkdate($matches[2], $matches[3], $matches[1])) {
            $reponse_filtre = $input;
        }
    }
    return $reponse_filtre;
}

function sanitize_dateTimeSQL($input)
{
    $reponse_filtre = null;
    // Vérifions si le format est valide
    if (preg_match('#^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$#', $input, $matches)) {
        // Vérifions si la date existe
        if (checkdate($matches[2], $matches[3], $matches[1])
    and (intval($matches[4])>-1) and (intval($matches[4])<24)
    and (intval($matches[5])>-1) and (intval($matches[5])<60)
    and (intval($matches[6])>-1) and (intval($matches[6])<60)) {
            $reponse_filtre = $input;
        }
    }
    return $reponse_filtre;
}

function sanitize_file_extension($input)
{
    $reponse_filtre = null;
    $extensions=array("xls","csv","pdf");
    if (in_array($input, $extensions)) {
        $reponse_filtre = $input;
    }
    return $reponse_filtre;
}

// sanitize_time retourne "00:00:00" par défaut
function sanitize_time($input)
{
    $reponse_filtre = "00:00:00";
    // Vérifions si le format est valide
    if (preg_match('#^(\d{1,2}):(\d{2}):(\d{2})$#', $input, $matches)) {
        $reponse_filtre = $input;
    }
    return $reponse_filtre;
}

// sanitize_on retourne false par défaut
// Permet par exemple de controler les checkboxes
function sanitize_on($input)
{
    $reponse_filtre = false;
    // Vérifions si le format est valide
    if ($input) {
        $reponse_filtre = true;
    }
    return $reponse_filtre;
}

// sanitize_on01 retourne 0 par défaut, sinon 1
// Permet par exemple de controler les checkboxes
function sanitize_on01($input)
{
    $reponse_filtre = 0;
    // Vérifions si le format est valide
    if ($input) {
        $reponse_filtre = 1;
    }
    return $reponse_filtre;
}
