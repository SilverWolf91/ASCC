/**
 * Datos geográficos de Colombia
 * Departamentos, municipios y veredas principales
 */

const colombiaData = {
    "Amazonas": {
        municipios: ["Leticia", "Puerto Nariño", "Otro"]
    },
    "Antioquia": {
        municipios: ["Medellín", "Bello", "Itagüí", "Envigado", "Rionegro", "Sabaneta", "La Estrella", "Caldas", "Copacabana", "Girardota", "Barbosa", "Apartadó", "Turbo", "Caucasia", "Puerto Berrío", "Yarumal", "Andes", "Otro"]
    },
    "Arauca": {
        municipios: ["Arauca", "Arauquita", "Cravo Norte", "Fortul", "Puerto Rondón", "Saravena", "Tame", "Otro"]
    },
    "Atlántico": {
        municipios: ["Barranquilla", "Soledad", "Malambo", "Sabanalarga", "Puerto Colombia", "Galapa", "Baranoa", "Palmar de Varela", "Otro"]
    },
    "Bolívar": {
        municipios: ["Cartagena", "Magangué", "Turbaco", "Arjona", "El Carmen de Bolívar", "Otro"]
    },
    "Boyacá": {
        municipios: ["Tunja", "Duitama", "Sogamoso", "Chiquinquirá", "Paipa", "Villa de Leyva", "Puerto Boyacá", "Otro"]
    },
    "Caldas": {
        municipios: ["Manizales", "Villamaría", "Chinchiná", "La Dorada", "Riosucio", "Anserma", "Otro"]
    },
    "Caquetá": {
        municipios: ["Florencia", "San Vicente del Caguán", "Puerto Rico", "El Doncello", "Otro"]
    },
    "Casanare": {
        municipios: ["Yopal", "Aguazul", "Villanueva", "Monterrey", "Tauramena", "Otro"]
    },
    "Cauca": {
        municipios: ["Popayán", "Santander de Quilichao", "Puerto Tejada", "Patía", "Otro"]
    },
    "Cesar": {
        municipios: ["Valledupar", "Aguachica", "Bosconia", "Codazzi", "La Paz", "Otro"]
    },
    "Chocó": {
        municipios: ["Quibdó", "Istmina", "Condoto", "Tadó", "Otro"]
    },
    "Córdoba": {
        municipios: ["Montería", "Cereté", "Lorica", "Sahagún", "Planeta Rica", "Otro"]
    },
    "Cundinamarca": {
        municipios: ["Bogotá", "Soacha", "Fusagasugá", "Facatativá", "Chía", "Zipaquirá", "Girardot", "Madrid", "Funza", "Mosquera", "Cajicá", "Tocancipá", "La Calera", "Cota", "Tenjo", "Tabio", "Otro"]
    },
    "Guainía": {
        municipios: ["Inírida", "Otro"]
    },
    "Guaviare": {
        municipios: ["San José del Guaviare", "Otro"]
    },
    "Huila": {
        municipios: ["Neiva", "Pitalito", "Garzón", "La Plata", "Campoalegre", "Otro"]
    },
    "La Guajira": {
        municipios: ["Riohacha", "Maicao", "Uribia", "Manaure", "Otro"]
    },
    "Magdalena": {
        municipios: ["Santa Marta", "Ciénaga", "Fundación", "El Banco", "Otro"]
    },
    "Meta": {
        municipios: ["Villavicencio", "Acacías", "Granada", "Puerto López", "San Martín", "Otro"]
    },
    "Nariño": {
        municipios: ["Pasto", "Tumaco", "Ipiales", "Túquerres", "Otro"]
    },
    "Norte de Santander": {
        municipios: ["Cúcuta", "Ocaña", "Pamplona", "Villa del Rosario", "Los Patios", "Otro"]
    },
    "Putumayo": {
        municipios: ["Mocoa", "Puerto Asís", "Orito", "Otro"]
    },
    "Quindío": {
        municipios: ["Armenia", "Calarcá", "La Tebaida", "Montenegro", "Quimbaya", "Otro"]
    },
    "Risaralda": {
        municipios: ["Pereira", "Dosquebradas", "Santa Rosa de Cabal", "La Virginia", "Otro"]
    },
    "San Andrés y Providencia": {
        municipios: ["San Andrés", "Providencia", "Otro"]
    },
    "Santander": {
        municipios: ["Bucaramanga", "Floridablanca", "Girón", "Piedecuesta", "Barrancabermeja", "San Gil", "Socorro", "Otro"]
    },
    "Sucre": {
        municipios: ["Sincelejo", "Corozal", "Sampués", "Otro"]
    },
    "Tolima": {
        municipios: ["Ibagué", "Espinal", "Melgar", "Líbano", "Honda", "Otro"]
    },
    "Valle del Cauca": {
        municipios: ["Cali", "Palmira", "Buenaventura", "Tuluá", "Cartago", "Buga", "Jamundí", "Yumbo", "Otro"]
    },
    "Vaupés": {
        municipios: ["Mitú", "Otro"]
    },
    "Vichada": {
        municipios: ["Puerto Carreño", "Otro"]
    }
};

// Veredas comunes (se pueden agregar más según necesidad)
const veredasComunes = [
    "Centro",
    "La Esperanza",
    "San José",
    "El Carmen",
    "La Palma",
    "El Paraíso",
    "Bella Vista",
    "San Antonio",
    "Santa Rosa",
    "La Florida",
    "Otro"
];