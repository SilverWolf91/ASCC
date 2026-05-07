<?php
/**
 * ═══════════════════════════════════════════════════════════
 * ASCC - API: OBTENER DEPARTAMENTOS
 * Ruta: controllers/api/get_departamentos.php
 * 
 * Retorna los 32 departamentos oficiales de Colombia
 * ═══════════════════════════════════════════════════════════
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

/**
 * 32 Departamentos oficiales de Colombia
 * Fuente: DANE (Departamento Administrativo Nacional de Estadística)
 */
$departamentos = [
    'Amazonas',
    'Antioquia',
    'Arauca',
    'Atlántico',
    'Bogotá D.C.',
    'Bolívar',
    'Boyacá',
    'Caldas',
    'Caquetá',
    'Casanare',
    'Cauca',
    'Cesar',
    'Chocó',
    'Córdoba',
    'Cundinamarca',
    'Guainía',
    'Guaviare',
    'Huila',
    'La Guajira',
    'Magdalena',
    'Meta',
    'Nariño',
    'Norte de Santander',
    'Putumayo',
    'Quindío',
    'Risaralda',
    'San Andrés y Providencia',
    'Santander',
    'Sucre',
    'Tolima',
    'Valle del Cauca',
    'Vaupés',
    'Vichada'
];

echo json_encode($departamentos, JSON_UNESCAPED_UNICODE);