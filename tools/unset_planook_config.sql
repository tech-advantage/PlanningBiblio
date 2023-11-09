-- -- WARNING --
-- -- This file disables the Planook configuration.
-- -- Uncomment all the lines and run the following queries if you want to use the fulll version of Planno after having used the lite version.

-- -- You may use the following command to uncomment the lines :
-- -- sed -i -E "s/^--(.*)$/\1/g" tools/unset_planook_config.sql
 
-- -- Désactivation du mode Planook
-- UPDATE `config` SET `valeur` = '0' WHERE `nom` = 'Planook';
-- UPDATE `config` SET `valeur` = 'default' WHERE `nom` = 'Affichage-theme';
-- -- Possibilité d'activer le dimanche
-- UPDATE config SET type='boolean' WHERE nom='Dimanche';
-- -- Granularité non-forcée :
-- UPDATE config SET type='enum2' WHERE nom='Granularite';
-- -- Absences
-- UPDATE config SET type='boolean' WHERE nom='Absences-blocage';
-- UPDATE config SET type='boolean' WHERE nom='Absences-planningVide';
-- UPDATE config SET type='boolean' WHERE nom='Absences-apresValidation';
-- UPDATE config SET type='boolean' WHERE nom='Absences-adminSeulement';
-- UPDATE config SET type='enum2' WHERE nom='Absences-planning';
-- UPDATE config SET type='boolean' WHERE nom='Absences-validation';
-- UPDATE config SET type='boolean' WHERE nom='Absences-non-validees';
-- UPDATE config SET type='boolean' WHERE nom='Absences-agent-preselection';
-- UPDATE config SET type='boolean' WHERE nom='Absences-tous';
-- UPDATE config SET type='boolean' WHERE nom='Absences-journeeEntiere';
-- UPDATE config SET type='boolean' WHERE nom='Absences-notifications-agent-par-agent';
-- UPDATE config SET type='text' WHERE nom='Absences-notifications-titre';
-- UPDATE config SET type='textarea' WHERE nom='Absences-notifications-message';
-- UPDATE config SET type='text' WHERE nom='Absences-DelaiSuppressionDocuments';
-- -- Affichage
-- UPDATE config SET type='text' WHERE nom='Affichage-titre';
-- UPDATE config SET type='boolean' WHERE nom='Affichage-etages';
-- -- Agenda
-- UPDATE config SET type='boolean' WHERE nom='Agenda-Plannings-Non-Valides';
-- -- Authentification
-- UPDATE config SET type='enum' WHERE nom='Auth-Mode';
-- UPDATE config SET type='boolean' WHERE nom='Auth-Anonyme';
-- -- Possibilité d'activer le module de gestion des congés
-- UPDATE config SET type='boolean' WHERE nom='Conges-Enable';
-- -- Possibilité d’importer des heures de présence
-- UPDATE config SET type='text' WHERE nom='Hamac-csv';
-- -- Possiblité d'activer le module Planning Hebdo
-- UPDATE config SET type='boolean' WHERE nom='PlanningHebdo';
-- -- Ne pas forcer un même emploi du temps chaque semaine
-- UPDATE config SET type='enum' WHERE nom='nb_semaine';
-- UPDATE config SET type='enum2' WHERE nom='EDTSamedi';
-- -- Ne pas interdire la 2ème pause
-- UPDATE config SET type='boolean' WHERE nom='PlanningHebdo-Pause2';
-- -- Permettre l'activation des imports ICS
-- UPDATE config SET type='text' WHERE nom='ICS-Server1';
-- UPDATE config SET type='text' WHERE nom='ICS-Server2';
-- UPDATE config SET type='text' WHERE nom='ICS-Pattern1';
-- UPDATE config SET type='text' WHERE nom='ICS-Pattern2';
-- UPDATE config SET type='boolean' WHERE nom='ICS-Server3';
-- -- Permet l'activation de l’export ICS
-- UPDATE config SET type='boolean' WHERE nom='ICS-Export';
-- -- Autoriser l’ajout de serveur LDAP
-- UPDATE config SET type='text' WHERE nom='LDAP-Host';
-- -- Autoriser l’activation de la messagerie
-- UPDATE config SET type='boolean' WHERE nom='Mail-IsEnabled';
-- -- Permettre l'activation des rappels
-- UPDATE config SET type='boolean' WHERE nom='Rappels-Actifs';
-- -- Statistiques
-- UPDATE config SET type='textarea' WHERE nom='Statistiques-Heures';
-- -- Préférence du planning
-- UPDATE config SET type='boolean' WHERE nom='ctrlHresAgents';
-- UPDATE config SET type='boolean' WHERE nom='CatAFinDeService';
-- UPDATE config SET type='enum' WHERE nom='Planning-NbAgentsCellule';
-- UPDATE config SET type='boolean' WHERE nom='Planning-lignesVides';
-- UPDATE config SET type='boolean' WHERE nom='toutlemonde';
-- UPDATE config SET type='boolean' WHERE nom='agentsIndispo';
-- UPDATE config SET type='boolean' WHERE nom='hres4semaines';
-- UPDATE config SET type='boolean' WHERE nom='ClasseParService';
-- UPDATE config SET type='boolean' WHERE nom='Planning-Absences-Heures-Hebdo';
-- UPDATE config SET type='boolean' WHERE nom='Planning-Notifications';
-- UPDATE config SET type='boolean' WHERE nom='Planning-TableauxMasques';
-- UPDATE config SET type='boolean' WHERE nom='Planning-AppelDispo';
-- UPDATE config SET type='boolean' WHERE nom='Planning-agents-volants';
-- UPDATE config SET type='text' WHERE nom='Journey-time-between-sites';
-- UPDATE config SET type='text' WHERE nom='Journey-time-between-areas';
-- UPDATE config SET type='text' WHERE nom='Journey-time-for-absences';
-- UPDATE config SET type='boolean' WHERE nom='Planning-CommentairesToujoursActifs';
