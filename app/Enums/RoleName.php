<?php

namespace App\Enums;

enum RoleName: string
{
    case Admin = 'Administrador';
    case Manager = 'Gestor';
    case Member = 'Membro';
}
