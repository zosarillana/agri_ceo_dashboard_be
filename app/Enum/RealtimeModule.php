<?php

namespace App\Enum;

enum RealtimeModule: string
{
    case ACCOUNT = 'account';
    case USER = 'user';
    case PRODUCT = 'product';
    case SALE = 'sale';
    case TRADE = 'trade';
    case PROCUREMENT = 'procurement';
    case PRODUCTION = 'production';
    case QC = 'qc';
    case MAINTENANCE = 'maintenance';
    case MAINTENANCE_LOG = 'maintenance_log';
    case ENERGY = 'energy';
    case WORKFORCE = 'workforce';
    case DEPARTMENT = 'department';
    case DASHBOARD = 'dashboard';
}