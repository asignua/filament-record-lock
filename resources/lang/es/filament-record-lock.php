<?php

declare(strict_types=1);

return [
    'modal' => [
        'heading_locked' => 'Este registro se está editando',
        'heading_taken_over' => 'Su bloqueo ha sido tomado',
        'heading_stale' => 'El registro ha cambiado',
        'locked' => ':user está editando este registro desde :since. Espere a que termine o ábralo en solo lectura.',
        'locked_unknown' => 'Otro usuario está editando este registro. Espere a que termine o ábralo en solo lectura.',
        'taken_over' => ':user ha tomado este registro. Sus cambios sin guardar ya no se pueden guardar aquí.',
        'taken_over_unknown' => 'Otro usuario ha tomado este registro. Sus cambios sin guardar ya no se pueden guardar aquí.',
        'stale' => 'Alguien cambió y guardó este registro mientras su bloqueo había caducado. Vuelva a cargarlo para continuar con los datos actuales.',
        'back' => 'Volver a la lista',
        'view' => 'Ver en solo lectura',
        'unlock' => 'Desbloquear y editar',
        'reload' => 'Recargar el registro',
    ],
    'notifications' => [
        'taken_over_title' => 'Su bloqueo ha sido tomado',
        'taken_over_body' => 'Otro usuario ha tomado este registro. Sus cambios sin guardar ya no se pueden guardar.',
        'save_refused_title' => 'No guardado',
        'save_refused_body' => 'Ya no tiene el bloqueo de este registro, por lo que sus cambios no se guardaron.',
        'unlock_denied' => 'No tiene permiso para desbloquear registros.',
    ],
    'actions' => [
        'release' => 'Liberar bloqueo',
        'release_heading' => '¿Liberar este bloqueo?',
        'release_description' => 'Quien edita el registro pierde el bloqueo y no podrá guardar sus cambios.',
        'released' => 'Bloqueo liberado',
        'release_selected' => 'Liberar bloqueos',
        'release_selected_heading' => '¿Liberar los bloqueos seleccionados?',
        'released_count' => 'Bloqueos liberados: :count',
        'release_all' => 'Liberar todos los bloqueos',
        'release_all_description' => 'Se elimina cada bloqueo y todos los que editan registros lo pierden.',
        'released_all' => 'Todos los bloqueos liberados',
    ],
    'resource' => [
        'navigation' => 'Bloqueos de registros',
        'singular' => 'bloqueo de registro',
        'plural' => 'Bloqueos de registros',
    ],
    'table' => [
        'type' => 'Tipo',
        'record' => 'Registro',
        'holder' => 'Bloqueado por',
        'since' => 'Bloqueado desde',
        'expires' => 'Caduca',
        'status' => 'Estado',
    ],
    'status' => [
        'active' => 'Activo',
        'expired' => 'Caducado',
    ],
    'column' => [
        'mine' => 'Está editando este registro',
        'other' => ':user está editando este registro (desde :since)',
        'other_unknown' => 'Bloqueado por otro usuario',
    ],
];
