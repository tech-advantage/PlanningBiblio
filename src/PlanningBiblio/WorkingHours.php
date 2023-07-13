<?php

namespace App\PlanningBiblio;

class WorkingHours
{
    private $times;
    private $breaks;

    function __construct($times, $breaks = array(), $free_break_already_removed = false)
    {
        $this->times = $times;
        $this->breaks = $breaks;
        $this->free_break_already_removed = $free_break_already_removed;
    }

    public function hoursOf($day)
    {

        /**
        * Tableau affichant les différentes possibilités
        * NB : le paramètre heures[4] est utilisé pour l'affectation du site. Il n'est pas utile ici
        * NB : la 2ème pause n'est pas implémentée depuis le début, c'est pourquoi les paramètres heures[5] et heures[6] viennent s'intercaler avant $heure[3]
        *
        *    Heure 0     Heure 1     Heure 2     Heure 5     Heure 6     Heure 3
        * 1                           [ tableau vide]
        * 2    |-----------|           |-----------|           |-----------|
        * 3    |-----------|           |-----------------------------------|
        * 4    |-----------|                                   |-----------|
        * 5    |-----------|
        * 6    |-----------------------------------|           |-----------|
        * 7    |-----------------------------------|
        * 8    |-----------------------------------------------------------|
        * 9                            |-----------|
        * 10                           |-----------------------------------|
        */


        $pause2 = $GLOBALS['config']['PlanningHebdo-Pause2'];

        if (!is_array($this->times)
            or empty($this->times)
            or !array_key_exists($day, $this->times)) {
            return array();
        }

        // Constitution des groupes de plages horaires
        $tab = array();
        $heures = $this->times[$day];
        $break = isset($this->breaks[$day]) ? $this->breaks[$day] : 0;

        // 1er créneau : cas N° 2; 3; 4; 5
        if (!empty($heures[0]) and !empty($heures[1])) {
            $tab[] = array($heures[0], $heures[1]);

        // 1er créneau fusionné avec le 2nd : cas N° 6 et 7
        } elseif ($pause2 and !empty($heures[0]) and !empty($heures[5])) {
            $tab[] = array($heures[0], $heures[5]);

        // Journée complète : cas N° 8
        } elseif (!empty($heures[0]) and !empty($heures[3])) {
            $tab[] = array($heures[0], $heures[3]);
        }

        // 2ème créneau : cas N° 2 et 9
        if ($pause2 and !empty($heures[2]) and !empty($heures[5])) {
            $tab[] = array($heures[2], $heures[5]);

        // 2ème créneau fusionné au 3ème : cas N° 3 et 10
        } elseif (!empty($heures[2]) and !empty($heures[3])) {
            $tab[] = array($heures[2], $heures[3]);
        }

        // 3ème créneau : cas N° 2; 4; 6
        if ($pause2 and !empty($heures[6]) and !empty($heures[3])) {
            $tab[] = array($heures[6], $heures[3]);
        }
        if ($break && !$this->free_break_already_removed) {
            // Free break is substracted at the end of the day
            $substracted = 0;
            foreach (array(2, 1, 0) as $i) {
                if (isset($tab[$i])) {
                    if (!$substracted) {
                        $tab[$i][1] = $this->substractBreak($tab[$i][1], $break);
                    }
                    $substracted = 1;

                    if ($i -1 >= 0 && strtotime($tab[$i][1]) <= strtotime($tab[$i -1][1])) {
                        $tab[$i -1][1] = $tab[$i][1];
                    }

                    if (strtotime($tab[$i][1]) <= strtotime($tab[$i][0])) {
                        unset($tab[$i]);
                    }
                }
            }
        }
        return $tab;
    }

    private function substractBreak($hour, $interval)
    {
         $minutes = $interval * 60;
         $new_hour = date('H:i:s', strtotime("- $minutes minutes $hour"));

         return $new_hour;
    }

    /**
     * getByDate
     @param String $date
     @param int $perso_id, optional. If not provided, the function will return working hours for everybody.
     @return array of working hours for the given date, indexed by perso_id, with agentId, workingHours and breakTime
     TODO: Handle working hours recorded in the agent files (param PlanningHebdo disabled)
     */
    public static function getByDate($date, $perso_id = null) {
        $wh = new \planningHebdo();
        if ($perso_id) {
            $wh->perso_id =  $date;
        }
        $wh->debut =  $date;
        $wh->fin =  $date;
        $wh->valide = true;
        $wh->fetch();
        $workingHours = $wh->elements;

        usort($workingHours, 'cmp_perso_id');

        $d = new \datePl($date);
        $week = $d->semaine3;

        $day = $d->position ? $d->position : 7;
        $day = $day + (($week - 1) * 7) - 1;

        $result = array();
        foreach ($workingHours as $elem) {
            $result[$elem['perso_id']] = (object) array(
                'agentId' => $elem['perso_id'],
                'workingHours' => $elem['temps'][$day] ?? [],
                'breakTime' => $elem['breaktime'][$day] ?? 0,
            );
       } 

       return $result;
    }
}
