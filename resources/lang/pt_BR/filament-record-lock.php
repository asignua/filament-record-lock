<?php

declare(strict_types=1);

return [
    'modal' => [
        'heading_locked' => 'Este registro está sendo editado',
        'heading_taken_over' => 'Seu bloqueio foi assumido',
        'heading_stale' => 'O registro foi alterado',
        'locked' => ':user está editando este registro desde :since. Aguarde até que termine ou abra-o somente para leitura.',
        'locked_unknown' => 'Outro usuário está editando este registro. Aguarde até que termine ou abra-o somente para leitura.',
        'taken_over' => ':user assumiu este registro. Suas alterações não salvas não podem mais ser salvas aqui.',
        'taken_over_unknown' => 'Outro usuário assumiu este registro. Suas alterações não salvas não podem mais ser salvas aqui.',
        'stale' => 'Alguém alterou e salvou este registro enquanto seu bloqueio havia expirado. Recarregue-o para continuar com os dados atuais.',
        'back' => 'Voltar à lista',
        'view' => 'Ver somente leitura',
        'unlock' => 'Desbloquear e editar',
        'reload' => 'Recarregar o registro',
    ],
    'notifications' => [
        'taken_over_title' => 'Seu bloqueio foi assumido',
        'taken_over_body' => 'Outro usuário assumiu este registro. Suas alterações não salvas não podem mais ser salvas.',
        'save_refused_title' => 'Não salvo',
        'save_refused_body' => 'Você não detém mais o bloqueio deste registro, então suas alterações não foram salvas.',
        'unlock_denied' => 'Você não tem permissão para desbloquear registros.',
    ],
    'actions' => [
        'release' => 'Liberar bloqueio',
        'release_heading' => 'Liberar este bloqueio?',
        'release_description' => 'Quem está editando o registro perde o bloqueio e não poderá salvar as alterações.',
        'released' => 'Bloqueio liberado',
        'release_selected' => 'Liberar bloqueios',
        'release_selected_heading' => 'Liberar os bloqueios selecionados?',
        'released_count' => 'Bloqueios liberados: :count',
        'release_all' => 'Liberar todos os bloqueios',
        'release_all_description' => 'Todos os bloqueios são excluídos e quem está editando registros os perde.',
        'released_all' => 'Todos os bloqueios liberados',
    ],
    'resource' => [
        'navigation' => 'Bloqueios de registros',
        'singular' => 'bloqueio de registro',
        'plural' => 'Bloqueios de registros',
    ],
    'table' => [
        'type' => 'Tipo',
        'record' => 'Registro',
        'holder' => 'Bloqueado por',
        'since' => 'Bloqueado desde',
        'expires' => 'Expira',
        'status' => 'Status',
    ],
    'status' => [
        'active' => 'Ativo',
        'expired' => 'Expirado',
    ],
    'column' => [
        'mine' => 'Você está editando este registro',
        'other' => ':user está editando este registro (desde :since)',
        'other_unknown' => 'Bloqueado por outro usuário',
    ],
];
