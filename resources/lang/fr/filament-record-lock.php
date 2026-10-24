<?php

declare(strict_types=1);

return [
    'modal' => [
        'heading_locked' => 'Cet enregistrement est en cours de modification',
        'heading_taken_over' => 'Votre verrou a été repris',
        'heading_stale' => 'L\'enregistrement a changé',
        'locked' => ':user modifie cet enregistrement depuis :since. Attendez qu\'il ait terminé ou ouvrez-le en lecture seule.',
        'locked_unknown' => 'Un autre utilisateur modifie cet enregistrement. Attendez qu\'il ait terminé ou ouvrez-le en lecture seule.',
        'taken_over' => ':user a repris cet enregistrement. Vos modifications non enregistrées ne peuvent plus être enregistrées ici.',
        'taken_over_unknown' => 'Un autre utilisateur a repris cet enregistrement. Vos modifications non enregistrées ne peuvent plus être enregistrées ici.',
        'stale' => 'Quelqu\'un a modifié et enregistré cet enregistrement pendant que votre verrou avait expiré. Rechargez-le pour continuer avec les données actuelles.',
        'back' => 'Retour à la liste',
        'view' => 'Voir en lecture seule',
        'unlock' => 'Déverrouiller et modifier',
        'reload' => 'Recharger l\'enregistrement',
    ],
    'notifications' => [
        'taken_over_title' => 'Votre verrou a été repris',
        'taken_over_body' => 'Un autre utilisateur a repris cet enregistrement. Vos modifications non enregistrées ne peuvent plus l\'être.',
        'save_refused_title' => 'Non enregistré',
        'save_refused_body' => 'Vous ne détenez plus le verrou de cet enregistrement, vos modifications n\'ont donc pas été enregistrées.',
        'unlock_denied' => 'Vous n\'êtes pas autorisé à déverrouiller des enregistrements.',
    ],
    'actions' => [
        'release' => 'Libérer le verrou',
        'release_heading' => 'Libérer ce verrou ?',
        'release_description' => 'La personne qui modifie l\'enregistrement perd le verrou et ne pourra pas enregistrer ses modifications.',
        'released' => 'Verrou libéré',
        'release_selected' => 'Libérer les verrous',
        'release_selected_heading' => 'Libérer les verrous sélectionnés ?',
        'released_count' => 'Verrous libérés : :count',
        'release_all' => 'Libérer tous les verrous',
        'release_all_description' => 'Chaque verrou est supprimé et toute personne en cours de modification le perd.',
        'released_all' => 'Tous les verrous ont été libérés',
    ],
    'resource' => [
        'navigation' => 'Verrous d\'enregistrements',
        'singular' => 'verrou d\'enregistrement',
        'plural' => 'Verrous d\'enregistrements',
    ],
    'table' => [
        'type' => 'Type',
        'record' => 'Enregistrement',
        'holder' => 'Verrouillé par',
        'since' => 'Verrouillé depuis',
        'expires' => 'Expire',
        'status' => 'Statut',
    ],
    'status' => [
        'active' => 'Actif',
        'expired' => 'Expiré',
    ],
    'column' => [
        'mine' => 'Vous modifiez cet enregistrement',
        'other' => ':user modifie cet enregistrement (depuis :since)',
        'other_unknown' => 'Verrouillé par un autre utilisateur',
    ],
];
