/**
 * ═══════════════════════════════════════════════════════════
 * ASCC - FORMULARIO CREAR PRODUCTO
 * Archivo : public/js/crear-producto.js
 *
 * CORRECCIONES en esta versión:
 *   - Bug nextStep: las líneas del final del IIFE ya NO
 *     sobreescriben window.nextStep. Solo init() lo define.
 *   - Autocomplete inteligente para municipios y veredas
 *   - Guarda nuevas ubicaciones en la BD al llegar al paso 7
 *   - Tolerante a errores ortográficos del campesino
 *   - [v3] buscarEnCatalogoEstatico: umbral 40, devuelve 8 resultados
 *   - [v3] Dropdown combinado: misma cat (✅) + otras cats (⚠️)
 *          Solo bloquea guardar si NO hay ningún resultado en misma cat.
 *
 * TODAS las funcionalidades anteriores MANTENIDAS:
 *   - formatPriceCOP, prevStep
 *   - Catálogo completo 12 categorías / 361 productos
 *   - Traducción bilingüe ES/EN
 *   - Mapa auto-ajustado según ubicación seleccionada
 *   - Paso 7: revisión completa antes de publicar
 * ═══════════════════════════════════════════════════════════
 */

(function (window) {
    'use strict';

    var currentStep = 1;
    var totalSteps = 7;
    var selectedCategory = null;
    var selectedProduct = null;

    // Timers para el debounce del autocomplete
    var _timerMunicipio = null;
    var _timerVereda = null;

    function getLang() {
        return document.documentElement.lang === 'en' ? 'en' : 'es';
    }

    /* ══════════════════════════════════════════════════════
       VERIFICADOR LOCAL DE PALABRAS BLOQUEADAS (drogas)
       Lista completa sincronizada con config/palabras_bloqueadas.php
       Soporta: mayúsculas, minúsculas, mezcla, leet speak (c0caina).
    ══════════════════════════════════════════════════════ */
    var _PALABRAS_BLOQUEADAS_JS = [
        /* Cocaína y derivados */
        'cocaina', 'coca', 'perico', 'perika', 'farlopa', 'blanca', 'polvo blanco',
        'linea', 'pase', 'pasecito', 'basuco', 'bazuco', 'pasta base', 'pasta de coca',
        'susto', 'mono', 'crack', 'piedra',
        /* Cannabis y derivados */
        'marihuana', 'mariguana', 'incienso salvaje', 'bareta', 'bareto', 'mota',
        'weed', 'creepy', 'krippy', 'cannabis', 'canabis', 'ganja', 'monte', 'porro', 'joint',
        'hachis', 'hash', 'wax', 'shatter',
        'resina de cannabis', 'aceite de cannabis', 'extracto de cannabis',
        /* Drogas sintéticas */
        'mdma', 'extasis', 'popper', 'poper', 'pop', 'tacha', 'pepa', 'molly',
        'cristal', 'crystal', 'hielo', 'meth', 'metanfetamina', 'anfetamina', 'speed',
        'anfeta', 'tripi', 'acido', 'lsd', 'carton', 'cuadro', 'ketamina', 'keta', 'special k',
        /* Opioides */
        'heroina', 'jaco', 'morfina', 'codeina', 'lean', 'jarabe morado',
        'fentanilo', 'fenta', 'oxicodona', 'oxy', 'oxi', 'opio',
        /* Inhalantes */
        'popper', 'poppers', 'nitrito', 'boxer', 'pegante', 'thinner',
        /* Nuevas sustancias psicoactivas */
        'spice', 'k2', 'cannabinoide sintetico', 'catinona', 'mefedrona',
        'nps', 'droga legal', 'hierba legal'
    ];

    function _normalizarBloqueadoJS(s) {
        return s
            .normalize('NFD').replace(/[̀-ͯ]/g, '')
            .toLowerCase()
            /* leet speak: números que sustituyen letras */
            .replace(/0/g, 'o').replace(/1/g, 'i').replace(/3/g, 'e')
            .replace(/4/g, 'a').replace(/5/g, 's').replace(/6/g, 'g')
            .replace(/7/g, 't').replace(/8/g, 'b').replace(/9/g, 'g')
            .trim();
    }

    function _verificarBloqueadoLocal(texto) {
        var norm = _normalizarBloqueadoJS(texto);
        var delim = '[\\s,.\\-_\\/\\\\()\\[\\]{}!?;:\'"]';
        for (var i = 0; i < _PALABRAS_BLOQUEADAS_JS.length; i++) {
            var pw = _normalizarBloqueadoJS(_PALABRAS_BLOQUEADAS_JS[i]);
            var escaped = pw.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            var patron = new RegExp('(^|' + delim + ')' + escaped + '($|' + delim + ')', 'u');
            if (patron.test(' ' + norm + ' ')) {
                return { bloqueado: true, palabra: _PALABRAS_BLOQUEADAS_JS[i] };
            }
        }
        return { bloqueado: false };
    }

    /* ══════════════════════════════════════════════════════
       NOMBRES DE CATEGORÍAS Y SUBCATEGORÍAS
    ══════════════════════════════════════════════════════ */

    var CATEGORIAS_NOMBRES = {
        es: {
            eggs_derivatives: 'Huevos y Derivados',
            poultry: 'Aves de Corral',
            cattle: 'Ganado Bovino',
            horses: 'Caballos y Equinos',
            small_livestock: 'Ganado Menor',
            meats_sausages: 'Cárnicos y Embutidos',
            dairy: 'Lácteos',
            vegetables: 'Verduras y Hortalizas',
            fruits: 'Frutas',
            cereals_grains: 'Cereales y Granos',
            plants_seeds: 'Plantas y Semillas',
            processed_products: 'Productos Procesados',
            fish: 'Peces y Acuicultura'
        },
        en: {
            eggs_derivatives: 'Eggs and Derivatives',
            poultry: 'Poultry',
            cattle: 'Cattle',
            horses: 'Horses and Equines',
            small_livestock: 'Small Livestock',
            meats_sausages: 'Meats and Sausages',
            dairy: 'Dairy',
            vegetables: 'Vegetables',
            fruits: 'Fruits',
            cereals_grains: 'Cereals and Grains',
            plants_seeds: 'Plants and Seeds',
            processed_products: 'Processed Products',
            fish: 'Fish and Aquaculture'
        }
    };

    var SUBCATEGORIAS_NOMBRES = {
        'Huevos de Gallina': { es: 'Huevos de Gallina', en: 'Hen Eggs' },
        'Derivados del Huevo': { es: 'Derivados del Huevo', en: 'Egg Derivatives' },
        'Gallinas': { es: 'Gallinas', en: 'Hens' },
        'Otras Aves': { es: 'Otras Aves', en: 'Other Birds' },
        'Razas Lecheras': { es: 'Razas Lecheras', en: 'Dairy Breeds' },
        'Razas de Carne': { es: 'Razas de Carne', en: 'Beef Breeds' },
        'Razas Doble Propósito': { es: 'Razas Doble Propósito', en: 'Dual Purpose Breeds' },
        'Animales de Trabajo': { es: 'Animales de Trabajo', en: 'Working Animals' },
        'Vacas': { es: 'Vacas', en: 'Cows' },
        'Toros y Novillos': { es: 'Toros y Novillos', en: 'Bulls and Steers' },
        'Caballos': { es: 'Caballos', en: 'Horses' },
        'Otros Equinos': { es: 'Otros Equinos', en: 'Other Equines' },
        'Porcinos Comerciales': { es: 'Razas Comerciales', en: 'Commercial Breeds' },
        'Porcinos Maternos': { es: 'Razas Maternas (Hembras)', en: 'Maternal Lines (Sows)' },
        'Porcinos Paternos': { es: 'Razas Paternas (Carne)', en: 'Paternal Lines (Meat)' },
        'Porcinos Híbridos': { es: 'Híbridos Comerciales', en: 'Commercial Hybrids' },
        'Porcinos Criollos': { es: 'Razas Criollas Colombianas', en: 'Colombian Creole Breeds' },
        'Porcinos Otros': { es: 'Otras Razas Porcinas', en: 'Other Pig Breeds' },
        'Caprinos': { es: 'Caprinos', en: 'Goats' },
        'Ovinos': { es: 'Ovinos', en: 'Sheep' },
        'Otros': { es: 'Otros', en: 'Others' },
        'Carnes Frescas': { es: 'Carnes Frescas', en: 'Fresh Meats' },
        'Embutidos': { es: 'Embutidos', en: 'Sausages' },
        'Otros Derivados': { es: 'Otros Derivados', en: 'Other Derivatives' },
        'Leche': { es: 'Leche', en: 'Milk' },
        'Derivados': { es: 'Derivados', en: 'Derivatives' },
        'Tubérculos y Raíces': { es: 'Tubérculos y Raíces', en: 'Tubers and Roots' },
        'Verduras de Hoja': { es: 'Verduras de Hoja', en: 'Leafy Vegetables' },
        'Frutos y Otros': { es: 'Frutos y Otros', en: 'Fruits and Others' },
        'Plantas Aromáticas': { es: 'Plantas Aromáticas', en: 'Aromatic Plants' },
        // ── PORCINOS ──
        'Porcinos Comerciales': { es: 'Razas Comerciales', en: 'Commercial Breeds' },
        'Porcinos Maternos': { es: 'Razas Maternas (Hembras)', en: 'Maternal Lines (Sows)' },
        'Porcinos Paternos': { es: 'Razas Paternas (Carne)', en: 'Paternal Lines (Meat)' },
        'Porcinos Híbridos': { es: 'Híbridos Comerciales', en: 'Commercial Hybrids' },
        'Porcinos Criollos': { es: 'Razas Criollas Colombianas', en: 'Colombian Creole Breeds' },
        'Porcinos Otros': { es: 'Otras Razas Porcinas', en: 'Other Pig Breeds' },
        // ── BANANOS Y PLÁTANOS ──
        'Bananos Criollos': { es: 'Bananos Criollos y Regionales', en: 'Creole and Regional Bananas' },
        'Bananos Comerciales': { es: 'Bananos Comerciales (Cavendish)', en: 'Commercial Bananas (Cavendish)' },
        'Plátanos Tradicionales': { es: 'Plátanos Tradicionales', en: 'Traditional Plantains' },
        'Plátanos Regionales': { es: 'Plátanos Regionales y Locales', en: 'Regional and Local Plantains' },
        'Plátanos Especiales': { es: 'Plátanos y Bananos Especiales', en: 'Special Plantains and Bananas' },
        // ── AGUACATES ──
        'Aguacates Comerciales': { es: 'Aguacates Comerciales', en: 'Commercial Avocados' },
        'Aguacates Criollos': { es: 'Aguacates Criollos y Tradicionales', en: 'Creole and Traditional Avocados' },
        'Aguacates Papelillo': { es: 'Aguacates Tipo Papelillo', en: 'Papelillo Type Avocados' },
        'Aguacates Otros': { es: 'Otras Variedades de Aguacate', en: 'Other Avocado Varieties' },
        // ── FRUTAS ──
        'Frutas Tropicales': { es: 'Frutas Tropicales', en: 'Tropical Fruits' },
        'Frutas de Clima Frío': { es: 'Frutas de Clima Frío', en: 'Cold Weather Fruits' },
        'Cítricos': { es: 'Cítricos', en: 'Citrus Fruits' },
        'Otras Frutas': { es: 'Otras Frutas', en: 'Other Fruits' },
        'Cereales': { es: 'Cereales', en: 'Cereals' },
        'Leguminosas': { es: 'Leguminosas', en: 'Legumes' },
        'Semillas Oleaginosas': { es: 'Semillas Oleaginosas', en: 'Oil Seeds' },
        'Plantas Medicinales': { es: 'Plantas Medicinales', en: 'Medicinal Plants' },
        'Semillas Certificadas': { es: 'Semillas Certificadas', en: 'Certified Seeds' },
        'Plántulas': { es: 'Plántulas', en: 'Seedlings' },
        'Flores y Ornamentales': { es: 'Flores y Ornamentales', en: 'Flowers and Ornamentals' },
        'Conservas y Encurtidos': { es: 'Conservas y Encurtidos', en: 'Preserves and Pickles' },
        'Bebidas Artesanales': { es: 'Bebidas Artesanales', en: 'Artisan Beverages' },
        'Harinas y Almidones': { es: 'Harinas y Almidones', en: 'Flours and Starches' },
        'Otros Procesados': { es: 'Otros Procesados', en: 'Other Processed' },
        // ── PECES ──
        'Tilapias': { es: 'Tilapias', en: 'Tilapia' },
        'Salmónidos': { es: 'Salmónidos (Aguas frías)', en: 'Salmonids (Cold water)' },
        'Cachamas y Especies Amazónicas': { es: 'Cachamas y Especies Amazónicas', en: 'Cachama and Amazonian Species' },
        'Bagres': { es: 'Bagres', en: 'Catfish' },
        'Especies Nativas de Río': { es: 'Especies Nativas de Río', en: 'Native River Species' },
        'Carpas': { es: 'Carpas', en: 'Carp' },
        'Especies Amazónicas y Orinoquía': { es: 'Especies Amazónicas y Orinoquía', en: 'Amazonian and Orinoco Species' },
        'Peces Ornamentales': { es: 'Peces Ornamentales (Exportación)', en: 'Ornamental Fish (Export)' },
        'Especies Marinas Cultivadas': { es: 'Especies Marinas Cultivadas', en: 'Farmed Marine Species' }
    };

    var PRODUCTOS_EN = {
        'Huevo AAA': 'AAA Egg', 'Huevo Extra': 'Extra Egg',
        'Huevo AA': 'AA Egg', 'Huevo A': 'A Egg', 'Huevo B': 'B Egg', 'Huevo C': 'C Egg',
        'Huevo Campesino': 'Farm Egg', 'Huevo Orgánico': 'Organic Egg',
        'Huevo de Codorniz': 'Quail Egg', 'Huevo de Pato': 'Duck Egg',
        'Huevo de Pava': 'Turkey Egg', 'Clara Pasteurizada': 'Pasteurized Egg White',
        'Yema Pasteurizada': 'Pasteurized Egg Yolk', 'Huevo en Polvo': 'Powdered Egg',
        'Gallina Ponedora': 'Laying Hen', 'Gallina de Engorde': 'Broiler Hen',
        'Gallina Criolla': 'Creole Hen', 'Pollo de Engorde': 'Broiler Chicken',
        'Pollo Campesino': 'Free-Range Chicken', 'Pollito Bebé': 'Baby Chick',
        'Pato': 'Duck', 'Pavo': 'Turkey', 'Ganso': 'Goose', 'Codorniz': 'Quail',
        'Paloma': 'Pigeon', 'Polla de Guinea': 'Guinea Fowl', 'Faisán': 'Pheasant',
        'Avestruz': 'Ostrich',
        'Holstein': 'Holstein', 'Jersey': 'Jersey', 'Pardo Suizo': 'Brown Swiss',
        'Ayrshire': 'Ayrshire', 'Guernsey': 'Guernsey', 'Gyr Lechero': 'Dairy Gyr',
        'Girolando': 'Girolando', 'Montbéliarde': 'Montbéliarde',
        'Rojo Sueco': 'Swedish Red', 'Fleckvieh': 'Fleckvieh', 'Lucerna': 'Lucerne',
        'Brahman': 'Brahman', 'Angus': 'Angus', 'Hereford': 'Hereford',
        'Charolais': 'Charolais', 'Limousin': 'Limousin', 'Brangus': 'Brangus',
        'Beefmaster': 'Beefmaster', 'Santa Gertrudis': 'Santa Gertrudis',
        'Romosinuano': 'Romosinuano', 'Blanco Azul Belga': 'Belgian Blue',
        'Nelore': 'Nellore', 'Senepol': 'Senepol',
        'Simmental': 'Simmental', 'Simbrah': 'Simbrah',
        'BON (Blanco Orejinegro)': 'BON (Blanco Orejinegro)',
        'Costeño con Cuernos': 'Costeño con Cuernos',
        'Hartón del Valle': 'Hartón del Valle', 'Sanmartinero': 'Sanmartinero',
        'Chino Santandereano': 'Chino Santandereano',
        'F1 Holstein x Brahman': 'F1 Holstein x Brahman',
        'F1 Jersey x Brahman': 'F1 Jersey x Brahman',
        'Toro Reproductor': 'Breeding Bull', 'Novillo de Ceba': 'Feeder Steer',
        'Buey de Trabajo': 'Working Ox', 'Ternero': 'Calf',
        'Caballo de Trabajo': 'Working Horse', 'Caballo de Paso Fino': 'Paso Fino Horse',
        'Caballo de Salto': 'Jumping Horse', 'Caballo de Polo': 'Polo Horse',
        'Yegua Reproductora': 'Breeding Mare', 'Potro': 'Colt',
        'Mula': 'Mule', 'Burro de Trabajo': 'Working Donkey', 'Asno': 'Donkey',
        'Pietrain': 'Pietrain', 'Duroc': 'Duroc', 'Landrace': 'Landrace',
        'Large White (Yorkshire)': 'Large White (Yorkshire)', 'Hampshire': 'Hampshire', 'Berkshire': 'Berkshire',
        'Chester White': 'Chester White', 'Poland China': 'Poland China', 'Spotted': 'Spotted',
        'F1 Yorkshire × Landrace': 'F1 Yorkshire × Landrace', 'F1 Landrace × Yorkshire': 'F1 Landrace × Yorkshire',
        'Terminales Duroc × Pietrain': 'Terminal Duroc × Pietrain', 'Terminales Pietrain × Hampshire': 'Terminal Pietrain × Hampshire',
        'Zungo': 'Zungo', 'Sanpedreño': 'Sanpedreno', 'Casco de Mula': 'Casco de Mula',
        'Tamworth': 'Tamworth', 'Hereford Porcino': 'Hereford (Pig)', 'Gloucestershire Old Spots': 'Gloucestershire Old Spots',
        'Mangalica': 'Mangalica', 'Ibérico': 'Iberico',
        'Cerdo de Ceba': 'Feeder Pig', 'Cerda Reproductora': 'Breeding Sow',
        'Lechón': 'Piglet', 'Cerdo Criollo': 'Creole Pig',
        'Cabra Lechera': 'Dairy Goat', 'Cabra de Carne': 'Meat Goat',
        'Macho Cabrío': 'Billy Goat', 'Cabrito': 'Kid Goat',
        'Oveja de Carne': 'Meat Sheep', 'Oveja de Lana': 'Wool Sheep',
        'Cordero': 'Lamb', 'Carnero': 'Ram',
        'Cuy': 'Guinea Pig', 'Conejo': 'Rabbit', 'Nutria': 'Otter',
        'Carne de Res': 'Beef', 'Carne de Cerdo': 'Pork',
        'Carne de Pollo': 'Chicken Meat', 'Carne de Cordero': 'Lamb Meat',
        'Carne de Cabra': 'Goat Meat', 'Carne de Conejo': 'Rabbit Meat',
        'Carne de Cuy': 'Guinea Pig Meat', 'Chorizo Campesino': 'Farm Chorizo',
        'Butifarra': 'Butifarra', 'Morcilla': 'Blood Sausage',
        'Chicharrón': 'Pork Crackling', 'Longaniza': 'Longaniza Sausage',
        'Salchicha Artesanal': 'Artisan Sausage', 'Tocino': 'Bacon',
        'Cuero de Cerdo': 'Pork Skin', 'Manteca de Cerdo': 'Lard',
        'Sebo de Res': 'Beef Tallow',
        'Leche Entera Cruda': 'Raw Whole Milk', 'Leche Pasteurizada': 'Pasteurized Milk',
        'Leche Descremada': 'Skim Milk', 'Leche de Cabra': 'Goat Milk',
        'Leche de Búfala': 'Buffalo Milk', 'Queso Campesino': 'Farm Cheese',
        'Queso Doble Crema': 'Double Cream Cheese', 'Queso Costeño': 'Coastal Cheese',
        'Queso Pera': 'Pear Cheese', 'Cuajada': 'Fresh Curd',
        'Yogur Artesanal': 'Artisan Yogurt', 'Mantequilla': 'Butter',
        'Suero Costeño': 'Coastal Whey', 'Kumis': 'Kumis',
        'Arequipe': 'Arequipe', 'Natilla': 'Natilla',
        'Papa Pastusa': 'Pastusa Potato', 'Papa Criolla': 'Criolla Potato',
        'Papa R12': 'R12 Potato', 'Papa Nevada': 'Nevada Potato',
        'Yuca': 'Cassava', 'Ñame': 'Yam', 'Arracacha': 'Arracacha',
        'Remolacha': 'Beetroot', 'Zanahoria': 'Carrot', 'Rábano': 'Radish',
        'Lechuga Batavia': 'Batavia Lettuce', 'Lechuga Romana': 'Romaine Lettuce',
        'Espinaca': 'Spinach', 'Acelga': 'Swiss Chard', 'Repollo': 'Cabbage',
        'Col de Bruselas': 'Brussels Sprouts', 'Apio': 'Celery',
        'Perejil': 'Parsley', 'Cilantro': 'Cilantro', 'Albahaca': 'Basil',
        'Tomate Chonto': 'Chonto Tomato', 'Tomate Cherry': 'Cherry Tomato',
        'Pepino': 'Cucumber', 'Berenjena': 'Eggplant', 'Pimentón': 'Bell Pepper',
        'Ají': 'Chili Pepper', 'Maíz Tierno': 'Fresh Corn', 'Habichuela': 'Green Bean',
        'Arveja Verde': 'Green Pea', 'Fríjol Verde': 'Green Bean Pod',
        'Cebolla Cabezona': 'Round Onion', 'Cebolla Larga': 'Spring Onion', 'Ajo': 'Garlic',
        'Hierbabuena': 'Spearmint', 'Menta': 'Mint', 'Orégano': 'Oregano',
        'Tomillo': 'Thyme', 'Romero': 'Rosemary', 'Laurel': 'Bay Leaf',
        'Banano': 'Banana', 'Plátano Hartón': 'Hartón Plantain',
        'Plátano Dominico': 'Dominico Plantain', 'Mango Tommy': 'Tommy Mango',
        'Mango de Azúcar': 'Sugar Mango', 'Papaya': 'Papaya', 'Piña': 'Pineapple',
        'Maracuyá': 'Passion Fruit', 'Guanábana': 'Soursop',
        'Tamarindo': 'Tamarind', 'Coco': 'Coconut',
        'Mora': 'Blackberry', 'Fresa': 'Strawberry', 'Uchuva': 'Cape Gooseberry',
        'Tomate de Árbol': 'Tree Tomato', 'Lulo': 'Lulo', 'Feijoa': 'Feijoa',
        'Curuba': 'Banana Passion Fruit', 'Granadilla': 'Granadilla',
        'Gulupa': 'Purple Passion Fruit',
        'Naranja': 'Orange', 'Mandarina': 'Mandarin', 'Limón Tahití': 'Tahiti Lime',
        'Limón Común': 'Common Lemon', 'Pomelo': 'Grapefruit', 'Toronja': 'Pomelo',
        // ── PORCINOS (traducciones) ──
        'Pietrain': 'Pietrain', 'Duroc': 'Duroc', 'Landrace': 'Landrace',
        'Large White (Yorkshire)': 'Large White (Yorkshire)', 'Hampshire': 'Hampshire',
        'Berkshire': 'Berkshire', 'Chester White': 'Chester White',
        'Poland China': 'Poland China', 'Spotted': 'Spotted',
        'F1 Yorkshire × Landrace': 'F1 Yorkshire × Landrace',
        'F1 Landrace × Yorkshire': 'F1 Landrace × Yorkshire',
        'Terminales Duroc × Pietrain': 'Terminal Duroc × Pietrain',
        'Terminales Pietrain × Hampshire': 'Terminal Pietrain × Hampshire',
        'Zungo': 'Zungo', 'Sanpedreño': 'Sanpedreno', 'Casco de Mula': 'Casco de Mula',
        'Tamworth': 'Tamworth', 'Hereford Porcino': 'Hereford (Pig)',
        'Gloucestershire Old Spots': 'Gloucestershire Old Spots',
        'Mangalica': 'Mangalica', 'Ibérico': 'Iberico',
        // ── BANANOS Y PLÁTANOS (traducciones) ──
        'Banano Criollo': 'Creole Banana', 'Banano Bocadillo': 'Bocadillo Banana',
        'Banano Seneguero': 'Seneguero Banana', 'Banano Manzano': 'Apple Banana',
        'Banano Rojo': 'Red Banana', 'Banano Guineo': 'Guineo Banana',
        'Banano Maqueño': 'Maqueño Banana', 'Banano Dominico': 'Dominico Banana',
        'Banano Gros Michel': 'Gros Michel Banana', 'Banano Poyo': 'Poyo Banana',
        'Banano Lacatán': 'Lacatan Banana', 'Banano Robusta': 'Robusta Banana',
        'Gran Enana': 'Grand Nain', 'Pequeña Enana': 'Dwarf Cavendish',
        'Williams': 'Williams Banana', 'Valery': 'Valery Banana',
        'Chinese Cavendish': 'Chinese Cavendish',
        'Hartón': 'Harton Plantain', 'Dominico-Hartón': 'Dominico-Harton',
        'Dominico': 'Dominico Plantain', 'Pelipita': 'Pelipita Plantain',
        'Morado': 'Purple Plantain', 'Cachaco': 'Cachaco Plantain',
        'Popocho': 'Popocho Plantain', 'Pompo': 'Pompo Plantain',
        'Maqueño': 'Maqueño Plantain', 'Guineo': 'Guineo Plantain', 'Trucho': 'Trucho Plantain',
        'Plátano Tolima': 'Tolima Plantain', 'Plátano Llanero': 'Llanero Plantain',
        'Plátano Tres Filos': 'Tres Filos Plantain', 'Plátano Colicero': 'Colicero Plantain',
        'Plátano Verde Llanero': 'Green Llanero Plantain', 'Plátano Guayabo': 'Guayabo Plantain',
        'Plátano Guineo Verde': 'Green Guineo Plantain', 'Plátano Enano': 'Dwarf Plantain',
        'Plátano Macho': 'Macho Plantain', 'Plátano Rojo': 'Red Plantain',
        'Plátano Arroz': 'Rice Plantain', 'Plátano Tabasco': 'Tabasco Plantain',
        'Plátano Seda': 'Silk Plantain', 'Plátano Cuadrado': 'Square Plantain',
        'Banano Pink': 'Pink Banana', 'Banano Baby': 'Baby Banana',
        // ── AGUACATES (traducciones) ──
        'Aguacate Hass': 'Hass Avocado', 'Aguacate Lorena': 'Lorena Avocado',
        'Hass': 'Hass Avocado', 'Fuerte': 'Fuerte Avocado', 'Choquette': 'Choquette Avocado',
        'Lorena': 'Lorena Avocado', 'Santana': 'Santana Avocado', 'Booth 8': 'Booth 8 Avocado',
        'Trinidad': 'Trinidad Avocado', 'Ettinger': 'Ettinger Avocado',
        'Colinred': 'Colinred Avocado', 'Trapo': 'Trapo Avocado',
        'Criollo': 'Creole Avocado', 'Paltón': 'Palton Avocado', 'Collie': 'Collie Avocado',
        'Criollo de Monte': 'Wild Creole Avocado', 'Papelillo': 'Papelillo Avocado',
        'Semil 40': 'Semil 40 Avocado', 'Edranol': 'Edranol Avocado',
        'Reed': 'Reed Avocado', 'Semil 34': 'Semil 34 Avocado', 'Lula': 'Lula Avocado',
        'Bacon': 'Bacon Avocado', 'Pinkerton': 'Pinkerton Avocado', 'Zutano': 'Zutano Avocado',
        'Guayaba': 'Guava', 'Manzana': 'Apple', 'Pera': 'Pear', 'Durazno': 'Peach',
        'Ciruela': 'Plum', 'Uva': 'Grape', 'Melón': 'Melon', 'Sandía': 'Watermelon',
        'Maíz Amarillo': 'Yellow Corn', 'Maíz Blanco': 'White Corn',
        'Maíz Peto': 'Peto Corn', 'Arroz Paddy': 'Paddy Rice',
        'Arroz Blanco': 'White Rice', 'Sorgo': 'Sorghum', 'Trigo': 'Wheat',
        'Cebada': 'Barley', 'Avena': 'Oats', 'Quinua': 'Quinoa',
        'Fríjol Cargamanto': 'Cargamanto Bean', 'Fríjol Bola Roja': 'Red Ball Bean',
        'Fríjol Cabecita Negra': 'Black-Eyed Pea', 'Lenteja': 'Lentil',
        'Garbanzo': 'Chickpea', 'Arveja Seca': 'Dried Pea', 'Soya': 'Soybean', 'Maní': 'Peanut',
        'Girasol': 'Sunflower', 'Ajonjolí': 'Sesame', 'Palma Africana': 'African Palm',
        'Sábila': 'Aloe Vera', 'Manzanilla': 'Chamomile', 'Valeriana': 'Valerian',
        'Caléndula': 'Calendula', 'Toronjil': 'Lemon Balm', 'Pasiflora': 'Passionflower',
        'Semilla de Maíz': 'Corn Seed', 'Semilla de Fríjol': 'Bean Seed',
        'Semilla de Papa': 'Potato Seed', 'Semilla de Arroz': 'Rice Seed',
        'Semilla de Tomate': 'Tomato Seed', 'Semilla de Pimentón': 'Bell Pepper Seed',
        'Plántula de Tomate': 'Tomato Seedling', 'Plántula de Pimentón': 'Bell Pepper Seedling',
        'Plántula de Lechuga': 'Lettuce Seedling', 'Plántula de Cebolla': 'Onion Seedling',
        'Plántula de Café': 'Coffee Seedling',
        'Rosa': 'Rose', 'Clavel': 'Carnation', 'Crisantemo': 'Chrysanthemum',
        'Orquídea': 'Orchid', 'Anturio': 'Anthurium',
        'Mermelada de Mora': 'Blackberry Jam', 'Mermelada de Fresa': 'Strawberry Jam',
        'Mermelada de Guayaba': 'Guava Jam', 'Bocadillo de Guayaba': 'Guava Candy',
        'Hogao': 'Hogao Sauce', 'Ají Casero': 'Homemade Hot Sauce', 'Encurtidos': 'Pickles',
        'Jugo de Caña': 'Sugar Cane Juice', 'Guarapo': 'Guarapo',
        'Chicha': 'Chicha', 'Masato': 'Masato', 'Café Tostado': 'Roasted Coffee',
        'Cacao en Polvo': 'Cocoa Powder', 'Panela': 'Panela', 'Miel de Abejas': 'Honey',
        'Harina de Maíz': 'Corn Flour', 'Almidón de Yuca': 'Cassava Starch',
        'Almidón de Achira': 'Achira Starch', 'Harina de Plátano': 'Plantain Flour',
        'Harina de Arroz': 'Rice Flour',
        'Aceite de Coco': 'Coconut Oil', 'Aceite de Palma': 'Palm Oil',
        'Vinagre de Frutas': 'Fruit Vinegar',
        // ── PECES ──
        'Tilapia nilótica': 'Nile Tilapia', 'Tilapia roja': 'Red Tilapia',
        'Tilapia plateada': 'Silver Tilapia', 'Tilapia híbrida': 'Hybrid Tilapia',
        'Trucha arcoíris': 'Rainbow Trout', 'Trucha marrón': 'Brown Trout', 'Salmón': 'Salmon',
        'Cachama blanca': 'White Cachama', 'Cachama negra': 'Black Cachama',
        'Tambaquí': 'Tambaqui', 'Pirapitinga': 'Pirapitinga', 'Pacú': 'Pacu',
        'Bagre rayado': 'Striped Catfish', 'Bagre de canal': 'Channel Catfish',
        'Bagre africano': 'African Catfish', 'Bagre pintado': 'Painted Catfish',
        'Nicuro': 'Nicuro', 'Capaz': 'Capaz',
        'Bocachico': 'Bocachico', 'Dorada (Brycon moorei)': 'Dorada (Brycon moorei)',
        'Yamú': 'Yamu', 'Doncella': 'Doncella', 'Moncholo': 'Moncholo',
        'Mojarra amarilla': 'Yellow Mojarra',
        'Carpa común': 'Common Carp', 'Carpa herbívora': 'Grass Carp',
        'Carpa plateada': 'Silver Carp', 'Carpa cabezona': 'Bighead Carp',
        'Arawana': 'Arowana', 'Paiche (Arapaima gigas)': 'Paiche (Arapaima gigas)',
        'Pintadillo': 'Pintadillo', 'Surubí': 'Surubi',
        'Escalar (pez ángel)': 'Angelfish', 'Disco': 'Discus', 'Guppy': 'Guppy',
        'Molly': 'Molly', 'Betta': 'Betta', 'Tetra': 'Tetra', 'Corydora': 'Corydora',
        'Róbalo': 'Snook', 'Pargo rojo': 'Red Snapper', 'Corvina': 'Sea Bass', 'Cobia': 'Cobia'
    };

    function traducirSubcategoria(nombreEs) {
        var lang = getLang();
        if (SUBCATEGORIAS_NOMBRES[nombreEs]) {
            return SUBCATEGORIAS_NOMBRES[nombreEs][lang] || nombreEs;
        }
        return nombreEs;
    }

    /* ══════════════════════════════════════════════════════
       CATÁLOGO COMPLETO
    ══════════════════════════════════════════════════════ */

    var CATALOGO = {
        huevos: {
            icon: '🥚', nombre: 'Huevos y Derivados', key: 'eggs_derivatives',
            productos: {
                'Huevos de Gallina': ['Huevo AAA', 'Huevo Extra', 'Huevo AA', 'Huevo A', 'Huevo B', 'Huevo C', 'Huevo Campesino', 'Huevo Orgánico', 'Huevo de Codorniz', 'Huevo de Pato', 'Huevo de Pava', 'Otro'],
                'Derivados del Huevo': ['Clara Pasteurizada', 'Yema Pasteurizada', 'Huevo en Polvo', 'Otro']
            }
        },
        aves: {
            icon: '🐔', nombre: 'Aves de Corral', key: 'poultry',
            productos: {
                'Gallinas': ['Gallina Ponedora', 'Gallina de Engorde', 'Gallina Criolla', 'Pollo de Engorde', 'Pollo Campesino', 'Pollito Bebé', 'Otro'],
                'Otras Aves': ['Pato', 'Pavo', 'Ganso', 'Codorniz', 'Paloma', 'Polla de Guinea', 'Faisán', 'Avestruz', 'Otro']
            }
        },
        bovinos: {
            icon: '🐄', nombre: 'Ganado Bovino', key: 'cattle',
            productos: {
                'Razas Lecheras': ['Holstein', 'Jersey', 'Pardo Suizo', 'Ayrshire', 'Guernsey', 'Gyr Lechero', 'Girolando', 'Normando', 'Montbéliarde', 'Rojo Sueco', 'Fleckvieh', 'Lucerna', 'Otro'],
                'Razas de Carne': ['Brahman', 'Angus', 'Hereford', 'Charolais', 'Limousin', 'Brangus', 'Beefmaster', 'Santa Gertrudis', 'Romosinuano', 'Blanco Azul Belga', 'Nelore', 'Senepol', 'Otro'],
                'Razas Doble Propósito': ['Simmental', 'Simbrah', 'BON (Blanco Orejinegro)', 'Costeño con Cuernos', 'Hartón del Valle', 'Sanmartinero', 'Chino Santandereano', 'Normando', 'F1 Holstein x Brahman', 'F1 Jersey x Brahman', 'Lucerna', 'Fleckvieh', 'Otro'],
                'Animales de Trabajo': ['Toro Reproductor', 'Novillo de Ceba', 'Buey de Trabajo', 'Ternero', 'Otro']
            }
        },
        equinos: {
            icon: '🐎', nombre: 'Caballos y Equinos', key: 'horses',
            productos: {
                'Caballos': ['Caballo de Trabajo', 'Caballo de Paso Fino', 'Caballo de Salto', 'Caballo de Polo', 'Yegua Reproductora', 'Potro', 'Otro'],
                'Otros Equinos': ['Mula', 'Burro de Trabajo', 'Asno', 'Otro']
            }
        },
        menor: {
            icon: '🐖', nombre: 'Ganado Menor', key: 'small_livestock',
            productos: {
                'Porcinos Comerciales': ['Pietrain', 'Duroc', 'Landrace', 'Large White (Yorkshire)', 'Hampshire', 'Berkshire', 'Chester White', 'Poland China', 'Spotted', 'Otro'],
                'Porcinos Maternos': ['Large White (Yorkshire)', 'Landrace', 'Chester White', 'Otro'],
                'Porcinos Paternos': ['Pietrain', 'Duroc', 'Hampshire', 'Berkshire', 'Otro'],
                'Porcinos Híbridos': ['F1 Yorkshire × Landrace', 'F1 Landrace × Yorkshire', 'Terminales Duroc × Pietrain', 'Terminales Pietrain × Hampshire', 'Otro'],
                'Porcinos Criollos': ['Zungo', 'Sanpedreño', 'Casco de Mula', 'Otro'],
                'Porcinos Otros': ['Tamworth', 'Hereford Porcino', 'Gloucestershire Old Spots', 'Mangalica', 'Ibérico', 'Otro'],
                'Caprinos': ['Cabra Lechera', 'Cabra de Carne', 'Macho Cabrío', 'Cabrito', 'Otro'],
                'Ovinos': ['Oveja de Carne', 'Oveja de Lana', 'Cordero', 'Carnero', 'Otro'],
                'Otros': ['Cuy', 'Conejo', 'Nutria', 'Otro']
            }
        },
        carnicos: {
            icon: '🥩', nombre: 'Cárnicos y Embutidos', key: 'meats_sausages',
            productos: {
                'Carnes Frescas': ['Carne de Res', 'Carne de Cerdo', 'Carne de Pollo', 'Carne de Cordero', 'Carne de Cabra', 'Carne de Conejo', 'Carne de Cuy', 'Otro'],
                'Embutidos': ['Chorizo Campesino', 'Butifarra', 'Morcilla', 'Chicharrón', 'Longaniza', 'Salchicha Artesanal', 'Tocino', 'Otro'],
                'Otros Derivados': ['Cuero de Cerdo', 'Manteca de Cerdo', 'Sebo de Res', 'Otro']
            }
        },
        lacteos: {
            icon: '🥛', nombre: 'Lácteos', key: 'dairy',
            productos: {
                'Leche': ['Leche Entera Cruda', 'Leche Pasteurizada', 'Leche Descremada', 'Leche de Cabra', 'Leche de Búfala', 'Otro'],
                'Derivados': ['Queso Campesino', 'Queso Doble Crema', 'Queso Costeño', 'Queso Pera', 'Cuajada', 'Yogur Artesanal', 'Mantequilla', 'Suero Costeño', 'Kumis', 'Arequipe', 'Natilla', 'Otro']
            }
        },
        verduras: {
            icon: '🥦', nombre: 'Verduras y Hortalizas', key: 'vegetables',
            productos: {
                'Tubérculos y Raíces': ['Papa Pastusa', 'Papa Criolla', 'Papa R12', 'Papa Nevada', 'Yuca', 'Ñame', 'Arracacha', 'Remolacha', 'Zanahoria', 'Rábano', 'Otro'],
                'Verduras de Hoja': ['Lechuga Batavia', 'Lechuga Romana', 'Espinaca', 'Acelga', 'Repollo', 'Col de Bruselas', 'Apio', 'Perejil', 'Cilantro', 'Albahaca', 'Otro'],
                'Frutos y Otros': ['Tomate Chonto', 'Tomate Cherry', 'Pepino', 'Berenjena', 'Pimentón', 'Ají', 'Maíz Tierno', 'Habichuela', 'Arveja Verde', 'Fríjol Verde', 'Cebolla Cabezona', 'Cebolla Larga', 'Ajo', 'Otro'],
                'Plantas Aromáticas': ['Hierbabuena', 'Menta', 'Orégano', 'Tomillo', 'Romero', 'Laurel', 'Otro']
            }
        },
        frutas: {
            icon: '🍎', nombre: 'Frutas', key: 'fruits',
            productos: {
                'Bananos Criollos': ['Banano Criollo', 'Banano Bocadillo', 'Banano Seneguero', 'Banano Manzano', 'Banano Rojo', 'Banano Guineo', 'Banano Maqueño', 'Banano Dominico', 'Banano Gros Michel', 'Banano Poyo', 'Banano Lacatán', 'Banano Robusta', 'Otro'],
                'Bananos Comerciales': ['Gran Enana', 'Pequeña Enana', 'Williams', 'Valery', 'Chinese Cavendish', 'Otro'],
                'Plátanos Tradicionales': ['Hartón', 'Dominico-Hartón', 'Dominico', 'Pelipita', 'Morado', 'Cachaco', 'Popocho', 'Pompo', 'Maqueño', 'Guineo', 'Trucho', 'Otro'],
                'Plátanos Regionales': ['Plátano Tolima', 'Plátano Llanero', 'Plátano Tres Filos', 'Plátano Colicero', 'Plátano Verde Llanero', 'Plátano Guayabo', 'Plátano Guineo Verde', 'Plátano Enano', 'Plátano Macho', 'Otro'],
                'Plátanos Especiales': ['Plátano Rojo', 'Plátano Arroz', 'Plátano Tabasco', 'Plátano Seda', 'Plátano Cuadrado', 'Banano Pink', 'Banano Baby', 'Otro'],
                'Aguacates Comerciales': ['Hass', 'Fuerte', 'Choquette', 'Lorena', 'Santana', 'Booth 8', 'Trinidad', 'Ettinger', 'Colinred', 'Trapo', 'Otro'],
                'Aguacates Criollos': ['Criollo', 'Paltón', 'Collie', 'Criollo de Monte', 'Otro'],
                'Aguacates Papelillo': ['Papelillo', 'Semil 40', 'Edranol', 'Booth 8', 'Choquette', 'Otro'],
                'Aguacates Otros': ['Reed', 'Semil 34', 'Lula', 'Bacon', 'Pinkerton', 'Zutano', 'Otro'],
                'Frutas Tropicales': ['Mango Tommy', 'Mango de Azúcar', 'Papaya', 'Piña', 'Maracuyá', 'Guanábana', 'Tamarindo', 'Coco', 'Guayaba', 'Melón', 'Sandía', 'Otro'],
                'Frutas de Clima Frío': ['Mora', 'Fresa', 'Uchuva', 'Tomate de Árbol', 'Lulo', 'Feijoa', 'Curuba', 'Granadilla', 'Gulupa', 'Otro'],
                'Cítricos': ['Naranja', 'Mandarina', 'Limón Tahití', 'Limón Común', 'Pomelo', 'Toronja', 'Otro'],
                'Otras Frutas': ['Manzana', 'Pera', 'Durazno', 'Ciruela', 'Uva', 'Otro']
            }
        },
        cereales: {
            icon: '🌾', nombre: 'Cereales y Granos', key: 'cereals_grains',
            productos: {
                'Cereales': ['Maíz Amarillo', 'Maíz Blanco', 'Maíz Peto', 'Arroz Paddy', 'Arroz Blanco', 'Sorgo', 'Trigo', 'Cebada', 'Avena', 'Quinua', 'Otro'],
                'Leguminosas': ['Fríjol Cargamanto', 'Fríjol Bola Roja', 'Fríjol Cabecita Negra', 'Lenteja', 'Garbanzo', 'Arveja Seca', 'Soya', 'Maní', 'Otro'],
                'Semillas Oleaginosas': ['Girasol', 'Ajonjolí', 'Palma Africana', 'Otro']
            }
        },
        plantas: {
            icon: '🌿', nombre: 'Plantas y Semillas', key: 'plants_seeds',
            productos: {
                'Plantas Medicinales': ['Sábila', 'Manzanilla', 'Valeriana', 'Caléndula', 'Toronjil', 'Pasiflora', 'Otro'],
                'Semillas Certificadas': ['Semilla de Maíz', 'Semilla de Fríjol', 'Semilla de Papa', 'Semilla de Arroz', 'Semilla de Tomate', 'Semilla de Pimentón', 'Otro'],
                'Plántulas': ['Plántula de Tomate', 'Plántula de Pimentón', 'Plántula de Lechuga', 'Plántula de Cebolla', 'Plántula de Café', 'Otro'],
                'Flores y Ornamentales': ['Rosa', 'Clavel', 'Crisantemo', 'Orquídea', 'Anturio', 'Otro']
            }
        },
        procesados: {
            icon: '🫙', nombre: 'Productos Procesados', key: 'processed_products',
            productos: {
                'Conservas y Encurtidos': ['Mermelada de Mora', 'Mermelada de Fresa', 'Mermelada de Guayaba', 'Bocadillo de Guayaba', 'Hogao', 'Ají Casero', 'Encurtidos', 'Otro'],
                'Bebidas Artesanales': ['Jugo de Caña', 'Guarapo', 'Chicha', 'Masato', 'Café Tostado', 'Cacao en Polvo', 'Panela', 'Miel de Abejas', 'Otro'],
                'Harinas y Almidones': ['Harina de Maíz', 'Almidón de Yuca', 'Almidón de Achira', 'Harina de Plátano', 'Harina de Arroz', 'Otro'],
                'Otros Procesados': ['Aceite de Coco', 'Aceite de Palma', 'Vinagre de Frutas', 'Otro']
            }
        },
        peces: {
            icon: '🐟', nombre: 'Peces y Acuicultura', key: 'fish',
            productos: {
                'Tilapias': ['Tilapia nilótica', 'Tilapia roja', 'Tilapia plateada', 'Tilapia híbrida', 'Otro'],
                'Salmónidos': ['Trucha arcoíris', 'Trucha marrón', 'Salmón', 'Otro'],
                'Cachamas y Especies Amazónicas': ['Cachama blanca', 'Cachama negra', 'Tambaquí', 'Pirapitinga', 'Pacú', 'Otro'],
                'Bagres': ['Bagre rayado', 'Bagre de canal', 'Bagre africano', 'Bagre pintado', 'Nicuro', 'Capaz', 'Otro'],
                'Especies Nativas de Río': ['Bocachico', 'Dorada (Brycon moorei)', 'Yamú', 'Doncella', 'Moncholo', 'Mojarra amarilla', 'Otro'],
                'Carpas': ['Carpa común', 'Carpa herbívora', 'Carpa plateada', 'Carpa cabezona', 'Otro'],
                'Especies Amazónicas y Orinoquía': ['Arawana', 'Paiche (Arapaima gigas)', 'Pintadillo', 'Surubí', 'Otro'],
                'Peces Ornamentales': ['Escalar (pez ángel)', 'Disco', 'Guppy', 'Molly', 'Betta', 'Tetra', 'Corydora', 'Otro'],
                'Especies Marinas Cultivadas': ['Róbalo', 'Pargo rojo', 'Corvina', 'Cobia', 'Otro']
            }
        }
    };

    /* ══════════════════════════════════════════════════════
       FORMATO PRECIO COP
    ══════════════════════════════════════════════════════ */

    function formatPriceCOP(input) {
        var cursorPos = input.selectionStart;
        var oldLength = input.value.length;
        var soloNumeros = input.value.replace(/\D/g, '');
        if (soloNumeros.length > 10) { soloNumeros = soloNumeros.substring(0, 10); }
        if (soloNumeros === '') { input.value = ''; return; }
        var formatted = soloNumeros.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        input.value = formatted;
        var newLength = input.value.length;
        var newCursor = cursorPos + (newLength - oldLength);
        if (newCursor < 0) { newCursor = 0; }
        input.setSelectionRange(newCursor, newCursor);
    }

    /* ══════════════════════════════════════════════════════
       MENSAJES DE VALIDACIÓN BILINGÜE
    ══════════════════════════════════════════════════════ */

    function msg(key) {
        var lang = getLang();
        var msgs = {
            es: {
                select_category: '⚠️ Por favor selecciona una categoría para continuar.',
                select_product: '⚠️ Por favor selecciona un producto para continuar.',
                fill_product_name: '⚠️ Por favor escribe el nombre del producto.',
                fill_description: '⚠️ Por favor escribe una descripción del producto.',
                fill_price: '⚠️ Por favor ingresa el precio del producto.',
                fill_quantity: '⚠️ Por favor ingresa la cantidad disponible.',
                fill_unit: '⚠️ Por favor selecciona la unidad de medida.',
                fill_department: '⚠️ Por favor selecciona el departamento.',
                fill_municipality: '⚠️ Por favor selecciona el municipio.',
                fill_village: '⚠️ Por favor selecciona la vereda.',
                fill_map: '⚠️ Por favor selecciona la ubicación en el mapa.',
                upload_image: '⚠️ Por favor sube al menos una imagen del producto.',
                write_custom_muni: '⚠️ Por favor escribe el nombre del municipio.',
                write_custom_vereda: '⚠️ Por favor escribe el nombre de la vereda.'
            },
            en: {
                select_category: '⚠️ Please select a category to continue.',
                select_product: '⚠️ Please select a product to continue.',
                fill_product_name: '⚠️ Please write the product name.',
                fill_description: '⚠️ Please write a product description.',
                fill_price: '⚠️ Please enter the product price.',
                fill_quantity: '⚠️ Please enter the available quantity.',
                fill_unit: '⚠️ Please select the unit of measure.',
                fill_department: '⚠️ Please select the department.',
                fill_municipality: '⚠️ Please select the municipality.',
                fill_village: '⚠️ Please select the village.',
                fill_map: '⚠️ Please select the location on the map.',
                upload_image: '⚠️ Please upload at least one product image.',
                write_custom_muni: '⚠️ Please write the municipality name.',
                write_custom_vereda: '⚠️ Please write the village name.'
            }
        };
        return (msgs[lang] || msgs['es'])[key] || key;
    }

    /* ══════════════════════════════════════════════════════
       TOAST DE ERROR
    ══════════════════════════════════════════════════════ */

    function mostrarError(mensaje) {
        var anterior = document.getElementById('ascc-toast');
        if (anterior) { anterior.remove(); }
        var toast = document.createElement('div');
        toast.id = 'ascc-toast';
        toast.style.cssText = 'position:fixed;bottom:30px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#EF4444,#DC2626);color:#fff;padding:16px 28px;border-radius:14px;font-size:15px;font-weight:700;z-index:99999;box-shadow:0 8px 24px rgba(239,68,68,0.5);animation:toastIn 0.3s ease;max-width:90vw;text-align:center';
        toast.textContent = mensaje;
        if (!document.getElementById('ascc-toast-style')) {
            var style = document.createElement('style');
            style.id = 'ascc-toast-style';
            style.textContent = '@keyframes toastIn{from{opacity:0;transform:translateX(-50%) translateY(20px)}to{opacity:1;transform:translateX(-50%) translateY(0)}}';
            document.head.appendChild(style);
        }
        document.body.appendChild(toast);
        setTimeout(function () { if (toast.parentNode) { toast.remove(); } }, 3500);
    }

    /* ══════════════════════════════════════════════════════
       VALIDACIÓN POR PASO
    ══════════════════════════════════════════════════════ */

    function validarPaso(paso) {
        switch (paso) {
            case 1:
                if (!selectedCategory) {
                    mostrarError(msg('select_category'));
                    var grid = document.getElementById('categoryGrid');
                    if (grid) {
                        grid.style.border = '3px solid #EF4444';
                        grid.style.borderRadius = '16px';
                        setTimeout(function () { grid.style.border = ''; grid.style.borderRadius = ''; }, 2000);
                    }
                    return false;
                }
                return true;

            case 2:
                if (!selectedProduct) {
                    mostrarError(msg('select_product'));
                    return false;
                }
                return true;

            case 3:
                var inputOtro = document.getElementById('customProductName');
                var otroVisible = document.getElementById('otherProductInput');
                if (otroVisible && otroVisible.style.display !== 'none') {
                    if (!inputOtro || inputOtro.value.trim() === '') {
                        mostrarError(msg('fill_product_name'));
                        if (inputOtro) { inputOtro.focus(); }
                        return false;
                    }
                }
                var descripcion = document.getElementById('descripcion');
                if (!descripcion || descripcion.value.trim().length < 10) {
                    mostrarError(msg('fill_description'));
                    if (descripcion) { descripcion.focus(); }
                    return false;
                }
                return true;

            case 4:
                var precio = document.getElementById('precio');
                var cantidad = document.getElementById('cantidad');
                var unidad = document.getElementById('unidad');
                if (!precio || precio.value.trim() === '') {
                    mostrarError(msg('fill_price'));
                    if (precio) { precio.focus(); }
                    return false;
                }
                if (!cantidad || cantidad.value.trim() === '' || parseInt(cantidad.value) < 1) {
                    mostrarError(msg('fill_quantity'));
                    if (cantidad) { cantidad.focus(); }
                    return false;
                }
                if (!unidad || unidad.value === '') {
                    mostrarError(msg('fill_unit'));
                    if (unidad) { unidad.focus(); }
                    return false;
                }
                return true;

            case 5:
                var depto = document.getElementById('departamento');
                var mpio = document.getElementById('municipio');
                var vereda = document.getElementById('vereda');
                var latInput = document.getElementById('lat');
                var mpioCustom = document.getElementById('municipio_custom');
                var veredaCustom = document.getElementById('vereda_custom');

                if (!depto || depto.value === '') {
                    mostrarError(msg('fill_department'));
                    if (depto) { depto.focus(); }
                    return false;
                }
                if (!mpio || mpio.value === '') {
                    mostrarError(msg('fill_municipality'));
                    if (mpio) { mpio.focus(); }
                    return false;
                }
                if (mpio.value === 'Otro (No aparece en la lista)') {
                    if (!mpioCustom || mpioCustom.value.trim() === '') {
                        mostrarError(msg('write_custom_muni'));
                        if (mpioCustom) { mpioCustom.focus(); }
                        return false;
                    }
                }
                if (!vereda || vereda.value === '') {
                    mostrarError(msg('fill_village'));
                    if (vereda) { vereda.focus(); }
                    return false;
                }
                if (vereda.value === 'Otro (No está en la lista)') {
                    if (!veredaCustom || veredaCustom.value.trim() === '') {
                        mostrarError(msg('write_custom_vereda'));
                        if (veredaCustom) { veredaCustom.focus(); }
                        return false;
                    }
                }
                if (!latInput || latInput.value === '') {
                    mostrarError(msg('fill_map'));
                    return false;
                }
                return true;

            default:
                return true;
        }
    }

    /* ══════════════════════════════════════════════════════
       NAVEGACIÓN DE PASOS (funciones internas)
    ══════════════════════════════════════════════════════ */

    function _nextStep() {
        if (!validarPaso(currentStep)) { return; }
        if (currentStep < totalSteps) {
            _setStep(currentStep + 1);
        }
    }

    function _prevStep() {
        if (currentStep > 1) {
            _setStep(currentStep - 1);
        }
    }

    function _setStep(nuevoStep) {
        var pasoActual = document.querySelector('.form-step[data-step="' + currentStep + '"]');
        if (pasoActual) { pasoActual.classList.remove('active'); }

        var indicadorActual = document.querySelector('.step[data-step="' + currentStep + '"]');
        if (indicadorActual) {
            indicadorActual.classList.remove('active');
            indicadorActual.classList.add('completed');
        }

        currentStep = nuevoStep;

        var nuevoPaso = document.querySelector('.form-step[data-step="' + currentStep + '"]');
        if (nuevoPaso) { nuevoPaso.classList.add('active'); }

        var nuevoIndicador = document.querySelector('.step[data-step="' + currentStep + '"]');
        if (nuevoIndicador) {
            nuevoIndicador.classList.remove('completed');
            nuevoIndicador.classList.add('active');
        }

        var container = document.querySelector('.form-container');
        if (container) {
            container.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        /* ── Mapa: iniciar lazy o refrescar al llegar al paso 5 ── */
        if (nuevoStep === 5 && typeof google !== 'undefined' && google.maps) {
            if (!window.asccMapInstance) {
                /* Primera vez: inicializar ahora que el div es visible */
                iniciarMapa();
            } else {
                /* Ya inicializado pero estaba oculto: forzar redibujado */
                google.maps.event.trigger(window.asccMapInstance, 'resize');
                if (window.asccMarkerInstance) {
                    window.asccMapInstance.setCenter(window.asccMarkerInstance.getPosition());
                } else {
                    window.asccMapInstance.setCenter({ lat: 4.5709, lng: -74.2973 });
                }
            }
        }
    }

    /* ══════════════════════════════════════════════════════
       RENDER DE CATEGORÍAS
    ══════════════════════════════════════════════════════ */

    function renderCategories() {
        var grid = document.getElementById('categoryGrid');
        if (!grid) { return; }
        var lang = getLang();
        grid.innerHTML = '';

        Object.keys(CATALOGO).forEach(function (key) {
            var cat = CATALOGO[key];
            var nombreTraducido = (CATEGORIAS_NOMBRES[lang] && CATEGORIAS_NOMBRES[lang][cat.key])
                ? CATEGORIAS_NOMBRES[lang][cat.key]
                : cat.nombre;

            var card = document.createElement('div');
            card.className = 'category-card';
            card.setAttribute('data-category', key);
            card.innerHTML =
                '<span class="category-icon">' + cat.icon + '</span>' +
                '<div class="category-name">' + nombreTraducido + '</div>';

            card.addEventListener('click', function () {
                document.querySelectorAll('.category-card').forEach(function (c) { c.classList.remove('selected'); });
                card.classList.add('selected');
                selectedCategory = key;
                var input = document.getElementById('categoria_principal');
                if (input) { input.value = key; }
            });

            grid.appendChild(card);
        });
    }

    /* ══════════════════════════════════════════════════════
       BÚSQUEDA EN CATÁLOGO ESTÁTICO (JS)
       Busca en TODOS los productos del CATALOGO sin importar
       en qué categoría esté el usuario. Detecta "zanahoria"
       aunque el usuario esté en Peces.
       ─────────────────────────────────────────────────────
       [v3] CAMBIOS:
         - Umbral bajado a 40 (antes 55) → detecta más coincidencias
         - Devuelve hasta 8 resultados (antes 5)
    ══════════════════════════════════════════════════════ */
    function buscarEnCatalogoEstatico(texto) {
        var resultados = [];
        var textoNorm = _normTexto(texto);
        if (textoNorm.length < 2) { return resultados; }

        Object.keys(CATALOGO).forEach(function (catKey) {
            var cat = CATALOGO[catKey];
            Object.keys(cat.productos).forEach(function (subcatKey) {
                cat.productos[subcatKey].forEach(function (prod) {
                    if (prod === 'Otro') { return; }
                    var prodNorm = _normTexto(prod);

                    /* Contiene (bonus alto) */
                    var contiene = (prodNorm.indexOf(textoNorm) !== -1 ||
                        textoNorm.indexOf(prodNorm) !== -1) ? 60 : 0;

                    /* Ratio caracteres comunes */
                    var comunes = 0;
                    for (var i = 0; i < textoNorm.length; i++) {
                        if (prodNorm.indexOf(textoNorm[i]) !== -1) { comunes++; }
                    }
                    var ratio = textoNorm.length > 0
                        ? (comunes / Math.max(textoNorm.length, prodNorm.length)) * 100
                        : 0;

                    var puntuacion = contiene + (ratio * 0.4);

                    /* ── [v3] Umbral bajado de 55 a 40 ── */
                    if (puntuacion >= 40) {
                        resultados.push({
                            producto: prod,
                            catKey: catKey,
                            subcatKey: subcatKey,
                            catNombre: cat.nombre,
                            catIcon: cat.icon,
                            puntuacion: Math.round(puntuacion)
                        });
                    }
                });
            });
        });

        resultados.sort(function (a, b) { return b.puntuacion - a.puntuacion; });
        /* ── [v3] Devuelve hasta 8 resultados (antes 5) ── */
        return resultados.slice(0, 8);
    }

    /* Helper: normalizar texto para comparaciones */
    function _normTexto(str) {
        str = str.toLowerCase().trim();
        var b = ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ', 'à', 'è', 'ì', 'ò', 'ù'];
        var r = ['a', 'e', 'i', 'o', 'u', 'u', 'n', 'a', 'e', 'i', 'o', 'u'];
        for (var i = 0; i < b.length; i++) { str = str.split(b[i]).join(r[i]); }
        return str;
    }

    /* ══════════════════════════════════════════════════════
       RENDER DE PRODUCTOS
    ══════════════════════════════════════════════════════ */

    /* Timer para debounce del autocomplete de productos */
    var _timerProducto = null;

    function renderProducts() {
        var container = document.getElementById('subcategoryContainer');
        if (!container || !selectedCategory) { return; }
        container.innerHTML = '';

        var cat = CATALOGO[selectedCategory];
        var lang = getLang();
        if (!cat) { return; }

        Object.keys(cat.productos).forEach(function (subcatName) {
            var productos = cat.productos[subcatName];
            var section = document.createElement('div');
            section.className = 'subcategory-section';

            var titulo = document.createElement('h4');
            titulo.textContent = cat.icon + ' ' + traducirSubcategoria(subcatName);
            section.appendChild(titulo);

            var lista = document.createElement('div');
            lista.className = 'product-list';

            /* ── ID único por subcategoría para el panel custom ── */
            var panelId = 'otro_panel_' + selectedCategory + '_' + subcatName.replace(/\s+/g, '_');

            productos.forEach(function (prod) {
                var item = document.createElement('div');
                item.className = 'product-item' + (prod === 'Otro' ? ' otro' : '');

                var prodMostrado = prod;
                if (lang === 'en') {
                    prodMostrado = (prod === 'Otro') ? 'Other ✨' : (PRODUCTOS_EN[prod] || prod);
                }
                item.textContent = prodMostrado;

                item.addEventListener('click', function () {
                    /* Ocultar todos los paneles custom del paso 2 */
                    document.querySelectorAll('.otro-producto-panel').forEach(function (p) {
                        p.style.display = 'none';
                    });
                    document.querySelectorAll('.product-item').forEach(function (p) { p.classList.remove('selected'); });
                    item.classList.add('selected');
                    selectedProduct = prod;

                    var hiddenSubcat = document.getElementById('subcategoria_hidden');
                    var hiddenProd = document.getElementById('producto_especifico');
                    var displayProd = document.getElementById('tipo_producto_display');
                    var otroContainer = document.getElementById('otherProductInput');

                    if (hiddenSubcat) { hiddenSubcat.value = subcatName; }
                    if (hiddenProd) { hiddenProd.value = prod; }
                    if (otroContainer) { otroContainer.style.display = 'none'; }

                    if (prod === 'Otro') {
                        /* Mostrar panel inline de "Otro" debajo de esta subcategoría */
                        var panel = document.getElementById(panelId);
                        if (panel) {
                            panel.style.display = 'block';
                            var inp = panel.querySelector('.otro-producto-input');
                            if (inp) {
                                inp.value = '';
                                inp.focus();
                                /* Reset botón y mensaje */
                                var btn = panel.querySelector('.btn-guardar-producto');
                                var ok = panel.querySelector('.producto-guardado-ok');
                                if (btn) { btn.style.display = 'none'; }
                                if (ok) { ok.style.display = 'none'; }
                            }
                        }
                        /* Limpiar display hasta que escriba */
                        if (displayProd) { displayProd.value = ''; }
                    } else {
                        if (displayProd) { displayProd.value = prod; }
                    }
                });

                lista.appendChild(item);
            });

            section.appendChild(lista);

            /* ══════════════════════════════════════════════
               PANEL "OTRO" — con drogas, categoría cruzada
               y mensajes completamente bilingüe ES / EN
               ─────────────────────────────────────────────
               [v3] CAMBIO EN PASO A:
               Antes: al encontrar el PRIMER resultado de otra
               categoría bloqueaba inmediatamente.
               Ahora: separa mismaCat[] y otrasCats[], construye
               dropdown combinado mostrando TODOS los resultados
               con badges de color. Solo bloquea guardar si NO
               hay ningún resultado en la misma categoría.
            ══════════════════════════════════════════════ */
            var panel = document.createElement('div');
            panel.id = panelId;
            panel.className = 'otro-producto-panel ubicacion-custom-container';
            panel.style.display = 'none';
            panel.innerHTML =
                '<label class="label-custom-ubicacion">✍️ ' +
                (lang === 'es' ? 'Escribe el nombre del producto' : 'Write the product name') +
                ' *</label>' +
                '<div class="input-custom-wrap" style="position:relative">' +
                '<input type="text" class="otro-producto-input input-custom-ubicacion" ' +
                'placeholder="' +
                (lang === 'es' ? 'Ej: Tilapia Azul, Bocachico rojo...' : 'Ex: Blue Tilapia, Red Bocachico...') +
                '" autocomplete="off">' +
                '<span class="input-custom-icono">🔍</span>' +
                '<div class="otro-producto-sugerencias ascc-sugerencias" style="display:none"></div>' +
                '</div>' +
                /* Alerta roja de drogas — oculta por defecto */
                '<div class="otro-producto-alerta-droga alerta-droga-bloqueo" style="display:none"></div>' +
                /* Warning naranja de categoría cruzada — oculto por defecto */
                '<div class="otro-producto-warning-cat alerta-categoria-cruzada" style="display:none"></div>' +
                '<p class="hint-custom-ubicacion">' +
                (lang === 'es'
                    ? '💡 Escribe despacio — buscamos en toda la plataforma.'
                    : '💡 Type slowly — we search across the entire platform.') +
                '</p>' +
                '<button type="button" class="btn-guardar-producto btn-guardar-ubicacion" style="display:none">' +
                '💾 ' + (lang === 'es' ? 'Guardar este producto' : 'Save this product') +
                '</button>' +
                '<div class="producto-guardado-ok ubicacion-guardada-ok" style="display:none">' +
                '✅ ' + (lang === 'es' ? '¡Producto guardado! Ya puedes continuar.' : 'Product saved! You can continue.') +
                '</div>';

            var inputOtro = panel.querySelector('.otro-producto-input');
            var sugsDiv = panel.querySelector('.otro-producto-sugerencias');
            var btnGuardar = panel.querySelector('.btn-guardar-producto');
            var okMsg = panel.querySelector('.producto-guardado-ok');
            var alertaDroga = panel.querySelector('.otro-producto-alerta-droga');
            var warningCat = panel.querySelector('.otro-producto-warning-cat');

            /* ── Helper: resetear todos los mensajes del panel ── */
            function _resetPanelMsgs() {
                alertaDroga.style.display = 'none';
                alertaDroga.innerHTML = '';
                warningCat.style.display = 'none';
                warningCat.innerHTML = '';
                okMsg.style.display = 'none';
            }

            /* ── Input: buscar similitudes + validar drogas ─── */
            inputOtro.addEventListener('input', function () {
                var valor = inputOtro.value.trim();
                var langNow = getLang();

                /* Actualizar hidden fields en tiempo real */
                var displayProd = document.getElementById('tipo_producto_display');
                var hiddenProd = document.getElementById('producto_especifico');
                if (displayProd) { displayProd.value = valor; }
                if (hiddenProd) { hiddenProd.value = valor; }
                selectedProduct = valor || 'Otro';

                _resetPanelMsgs();
                sugsDiv.style.display = 'none';
                btnGuardar.style.display = valor.length >= 2 ? 'block' : 'none';
                inputOtro.classList.remove('input-bloqueado');

                /* Debounce 500ms */
                clearTimeout(_timerProducto);
                _timerProducto = setTimeout(function () {
                    if (valor.length < 2) { return; }

                    /* ════════════════════════════════════════════
                       PASO 0 — Verificación LOCAL de drogas
                       Corre ANTES del catálogo para que nunca se
                       salte la revisión aunque el término coincida
                       con algo en el catálogo estático (return de
                       PASO A nunca llamaría a PASO B sin esto).
                    ════════════════════════════════════════════ */
                    var _checkLocal = _verificarBloqueadoLocal(valor);
                    if (_checkLocal.bloqueado) {
                        var _msgEs = '🚫 El término <strong>"' + _checkLocal.palabra + '"</strong> está bloqueado. ' +
                            'No se permiten sustancias psicoactivas ni drogas ilícitas en esta plataforma.';
                        var _msgEn = '🚫 The term <strong>"' + _checkLocal.palabra + '"</strong> is blocked. ' +
                            'Psychoactive substances and illegal drugs are not allowed on this platform.';
                        alertaDroga.innerHTML = langNow === 'es' ? _msgEs : _msgEn;
                        alertaDroga.style.display = 'block';
                        btnGuardar.style.display = 'none';
                        inputOtro.classList.add('input-bloqueado');
                        selectedProduct = 'Otro';
                        return;
                    }

                    /* ════════════════════════════════════════════
                       PASO A — Buscar en CATÁLOGO ESTÁTICO JS
                       ─────────────────────────────────────────
                       [v3] NUEVO COMPORTAMIENTO:
                       - Recoge TODOS los resultados del catálogo
                       - Los separa en mismaCat[] y otrasCats[]
                       - Construye un único dropdown combinado:
                           ✅ verde  → misma categoría (clic selecciona)
                           ⚠️ naranja → otra categoría (clic navega)
                       - Solo bloquea guardar si mismaCat es vacío
                         Y hay al menos un resultado en otrasCats.
                       - Si hay mezcla → permite guardar.
                    ════════════════════════════════════════════ */
                    var encontradosCatalogo = buscarEnCatalogoEstatico(valor);

                    if (encontradosCatalogo.length > 0) {
                        /* Separar resultados por categoría */
                        var mismaCat = [];
                        var otrasCats = [];
                        for (var k = 0; k < encontradosCatalogo.length; k++) {
                            if (encontradosCatalogo[k].catKey === selectedCategory) {
                                mismaCat.push(encontradosCatalogo[k]);
                            } else {
                                otrasCats.push(encontradosCatalogo[k]);
                            }
                        }

                        /* Construir dropdown combinado */
                        var tituloCat = langNow === 'es'
                            ? '🔍 Productos similares encontrados'
                            : '🔍 Similar products found';
                        var htmlCat = '<div class="sugerencias-titulo">' + tituloCat + '</div>';

                        encontradosCatalogo.forEach(function (r) {
                            var esMisma = (r.catKey === selectedCategory);
                            var catNomTrad = (CATEGORIAS_NOMBRES[langNow] && CATALOGO[r.catKey])
                                ? (CATEGORIAS_NOMBRES[langNow][CATALOGO[r.catKey].key] || r.catNombre)
                                : r.catNombre;
                            var prodMostrado = (langNow === 'en' && PRODUCTOS_EN[r.producto])
                                ? PRODUCTOS_EN[r.producto]
                                : r.producto;
                            var icono = esMisma ? '✅' : r.catIcon;
                            var claseItem = 'sugerencia-item' + (esMisma ? '' : ' sugerencia-otra-cat');
                            var msgCat = esMisma
                                ? (langNow === 'es'
                                    ? '📂 Misma categoría — clic para seleccionar directamente'
                                    : '📂 Same category — click to select directly')
                                : (langNow === 'es'
                                    ? '⚠️ Pertenece a <strong>' + catNomTrad + '</strong> — clic para ir a esa categoría'
                                    : '⚠️ Belongs to <strong>' + catNomTrad + '</strong> — click to go to that category');

                            htmlCat +=
                                '<div class="' + claseItem + '" ' +
                                'data-destcat="' + r.catKey + '" ' +
                                'data-destsub="' + r.subcatKey + '" ' +
                                'data-destprod="' + r.producto + '">' +
                                '<div class="sugerencia-fila-top">' +
                                '<span class="sugerencia-nombre">' + icono + ' ' + prodMostrado + '</span>' +
                                '<span class="sugerencia-pct">' + r.puntuacion + '%</span>' +
                                '</div>' +
                                '<div class="sugerencia-msg-cat">' + msgCat + '</div>' +
                                '</div>';
                        });

                        sugsDiv.innerHTML = htmlCat;
                        sugsDiv.style.display = 'block';

                        /* Eventos de clic en cada sugerencia del catálogo */
                        sugsDiv.querySelectorAll('.sugerencia-item').forEach(function (si) {
                            si.addEventListener('click', function () {
                                var dc = si.getAttribute('data-destcat');
                                var ds = si.getAttribute('data-destsub');
                                var dp = si.getAttribute('data-destprod');

                                sugsDiv.style.display = 'none';
                                _resetPanelMsgs();

                                if (dc === selectedCategory) {
                                    /* Misma categoría → seleccionar item directamente */
                                    var items = document.querySelectorAll('.product-item');
                                    items.forEach(function (it) {
                                        var textoIt = it.textContent.trim()
                                            .replace(' ✨', '').replace('Other ✨', 'Otro');
                                        if (textoIt === dp || textoIt === (PRODUCTOS_EN[dp] || dp)) {
                                            it.click();
                                            it.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                        }
                                    });
                                } else {
                                    /* Otra categoría → navegar automáticamente */
                                    selectedCategory = dc;
                                    var inpCat = document.getElementById('categoria_principal');
                                    if (inpCat) { inpCat.value = dc; }
                                    document.querySelectorAll('.category-card').forEach(function (c) {
                                        c.classList.toggle('selected', c.getAttribute('data-category') === dc);
                                    });
                                    _setStep(2);
                                    renderProducts();
                                    setTimeout(function () {
                                        var items2 = document.querySelectorAll('.product-item');
                                        items2.forEach(function (it) {
                                            var textoIt = it.textContent.trim()
                                                .replace(' ✨', '').replace('Other ✨', 'Otro');
                                            if (textoIt === dp || textoIt === (PRODUCTOS_EN[dp] || dp)) {
                                                it.click();
                                                it.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                            }
                                        });
                                    }, 150);
                                }
                            });
                        });

                        /* Solo bloquear guardar si NO hay resultados en la misma categoría */
                        if (mismaCat.length === 0 && otrasCats.length > 0) {
                            btnGuardar.style.display = 'none';
                            inputOtro.classList.add('input-bloqueado');
                            selectedProduct = 'Otro';

                            var warnEs = '⚠️ Los productos similares pertenecen a otras categorías.<br>' +
                                'Selecciona uno de la lista para ir a la categoría correcta, ' +
                                'o regresa al <strong>Paso 1</strong> y elige otra categoría.';
                            var warnEn = '⚠️ Similar products belong to other categories.<br>' +
                                'Select one from the list to go to the correct category, ' +
                                'or go back to <strong>Step 1</strong> and choose another category.';
                            warningCat.innerHTML = '<div class="warning-cat-texto">' + (langNow === 'es' ? warnEs : warnEn) + '</div>';
                            warningCat.style.display = 'block';
                        }
                        /* Si hay mezcla (mismaCat.length > 0) → permitir guardar (ya está visible) */

                        return; /* No continuar a la API */
                    }

                    /* ════════════════════════════════════════════
                       PASO B — Buscar en BD (productos_custom)
                       Para detectar productos ya guardados por
                       otros usuarios y evitar duplicados.
                    ════════════════════════════════════════════ */
                    inputOtro.classList.remove('input-bloqueado');

                    var url = '/ascc/api/buscar_producto.php' +
                        '?texto=' + encodeURIComponent(valor) +
                        '&categoria=' + encodeURIComponent(selectedCategory) +
                        '&subcategoria=' + encodeURIComponent(subcatName) +
                        '&lang=' + encodeURIComponent(langNow);

                    fetch(url)
                        .then(function (r) { return r.json(); })
                        .then(function (resp) {

                            /* ══ DROGA DETECTADA ══ */
                            if (resp && resp.bloqueado) {
                                alertaDroga.innerHTML = langNow === 'es' ? resp.mensaje_es : resp.mensaje_en;
                                alertaDroga.style.display = 'block';
                                btnGuardar.style.display = 'none';
                                inputOtro.classList.add('input-bloqueado');
                                selectedProduct = 'Otro';
                                return;
                            }

                            inputOtro.classList.remove('input-bloqueado');

                            /* ══ SIN SUGERENCIAS EN BD ══ */
                            if (!resp || resp.length === 0) {
                                sugsDiv.style.display = 'none';
                                return;
                            }

                            /* ══ SUGERENCIAS DE BD ══ */
                            var titulo = langNow === 'es'
                                ? '🔍 ¿Quisiste decir alguno de estos?'
                                : '🔍 Did you mean one of these?';
                            var html = '<div class="sugerencias-titulo">' + titulo + '</div>';

                            resp.forEach(function (s) {
                                var mensaje = langNow === 'es' ? s.mensaje_es : s.mensaje_en;
                                var iconoS = s.misma_subcategoria ? '✅' : (s.misma_categoria ? '📂' : '📦');
                                html +=
                                    '<div class="sugerencia-item' + (!s.misma_categoria ? ' sugerencia-otra-cat' : '') +
                                    '" data-valor="' + s.valor +
                                    '" data-cat="' + s.categoria_original +
                                    '" data-subcat="' + s.subcategoria_original + '">' +
                                    '<div class="sugerencia-fila-top">' +
                                    '<span class="sugerencia-nombre">' + iconoS + ' ' + s.valor + '</span>' +
                                    '<span class="sugerencia-pct">' + s.similitud + '%</span>' +
                                    '</div>' +
                                    '<div class="sugerencia-msg-cat">' + mensaje + '</div>' +
                                    '</div>';
                            });

                            sugsDiv.innerHTML = html;
                            sugsDiv.style.display = 'block';

                            sugsDiv.querySelectorAll('.sugerencia-item').forEach(function (si) {
                                si.addEventListener('click', function () {
                                    var elegido = si.getAttribute('data-valor');
                                    var catOrigen = si.getAttribute('data-cat');
                                    inputOtro.value = elegido;
                                    sugsDiv.style.display = 'none';
                                    btnGuardar.style.display = 'none';
                                    _resetPanelMsgs();

                                    if (catOrigen !== selectedCategory) {
                                        var warnLang = getLang();
                                        var wMsg = warnLang === 'es'
                                            ? '⚠️ "' + elegido + '" pertenece a otra categoría. Regresa al Paso 1 para seleccionarla.'
                                            : '⚠️ "' + elegido + '" belongs to another category. Go back to Step 1 to select it.';
                                        warningCat.innerHTML = '<div class="warning-cat-texto">' + wMsg + '</div>';
                                        warningCat.style.display = 'block';
                                        selectedProduct = 'Otro';
                                    } else {
                                        okMsg.style.display = 'block';
                                        selectedProduct = elegido;
                                        var dpEl = document.getElementById('tipo_producto_display');
                                        var hpEl = document.getElementById('producto_especifico');
                                        if (dpEl) { dpEl.value = elegido; }
                                        if (hpEl) { hpEl.value = elegido; }
                                    }
                                });
                            });
                        })
                        .catch(function () { });
                }, 500);
            });

            /* ── Ocultar sugerencias al perder foco ─────────── */
            inputOtro.addEventListener('blur', function () {
                setTimeout(function () { sugsDiv.style.display = 'none'; }, 300);
            });

            /* ── Botón guardar producto nuevo ───────────────── */
            btnGuardar.addEventListener('click', function () {
                var valor = inputOtro.value.trim();
                var langNow = getLang();
                if (!valor) { return; }

                btnGuardar.disabled = true;
                btnGuardar.textContent = '⏳ ' + (langNow === 'es' ? 'Guardando...' : 'Saving...');

                fetch('/ascc/api/guardar_producto.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        nombre: valor,
                        categoria: selectedCategory,
                        subcategoria: subcatName,
                        lang: langNow
                    })
                })
                    .then(function (r) { return r.json(); })
                    .then(function (resp) {
                        /* ══ DROGA DETECTADA EN SERVIDOR ══ */
                        if (resp.bloqueado) {
                            var msgD = langNow === 'es' ? resp.mensaje_es : resp.mensaje_en;
                            alertaDroga.innerHTML = msgD;
                            alertaDroga.style.display = 'block';
                            btnGuardar.style.display = 'none';
                            inputOtro.classList.add('input-bloqueado');
                            return;
                        }

                        /* ══ GUARDADO EXITOSO O YA EXISTÍA ══ */
                        if (resp.ok) {
                            var msgOk = langNow === 'es' ? resp.mensaje_es : resp.mensaje_en;
                            btnGuardar.style.display = 'none';
                            okMsg.innerHTML = msgOk;
                            okMsg.style.display = 'block';
                            inputOtro.classList.remove('input-bloqueado');
                            selectedProduct = valor;
                            var dp = document.getElementById('tipo_producto_display');
                            var hp = document.getElementById('producto_especifico');
                            if (dp) { dp.value = valor; }
                            if (hp) { hp.value = valor; }
                        }
                    })
                    .catch(function () { })
                    .finally(function () {
                        btnGuardar.disabled = false;
                        btnGuardar.textContent = '💾 ' + (getLang() === 'es' ? 'Guardar este producto' : 'Save this product');
                    });
            });

            section.appendChild(panel);
            container.appendChild(section);
        });
    }

    /* ══════════════════════════════════════════════════════
       VALIDACIÓN FINAL AL ENVIAR EL FORMULARIO
    ══════════════════════════════════════════════════════ */

    function validarFormularioCompleto(e) {
        var imagenes = document.getElementById('imagenes');
        if (!imagenes || imagenes.files.length === 0) {
            e.preventDefault();
            mostrarError(msg('upload_image'));
            return false;
        }

        // Limpiar separadores del precio antes de enviar
        var precioInput = document.getElementById('precio');
        if (precioInput) {
            precioInput.value = precioInput.value.replace(/\./g, '');
        }

        // Si el select de municipio aún tiene "Otro...", inyectar el valor custom como opción y seleccionarlo
        var mpioSelect = document.getElementById('municipio');
        var mpioCustom = document.getElementById('municipio_custom');
        if (mpioSelect && mpioSelect.value === 'Otro (No aparece en la lista)' && mpioCustom && mpioCustom.value.trim() !== '') {
            var mpVal = mpioCustom.value.trim();
            if (!mpioSelect.querySelector('option[value="' + mpVal + '"]')) {
                var mpOpt = document.createElement('option');
                mpOpt.value = mpVal;
                mpOpt.textContent = mpVal;
                mpioSelect.appendChild(mpOpt);
            }
            mpioSelect.value = mpVal;
        }

        // Si el select de vereda aún tiene "Otro...", inyectar el valor custom como opción y seleccionarlo
        var veredaSelect = document.getElementById('vereda');
        var veredaCustom = document.getElementById('vereda_custom');
        if (veredaSelect && veredaSelect.value === 'Otro (No está en la lista)' && veredaCustom && veredaCustom.value.trim() !== '') {
            var vrVal = veredaCustom.value.trim();
            if (!veredaSelect.querySelector('option[value="' + vrVal + '"]')) {
                var vrOpt = document.createElement('option');
                vrOpt.value = vrVal;
                vrOpt.textContent = vrVal;
                veredaSelect.appendChild(vrOpt);
            }
            veredaSelect.value = vrVal;
        }

        e.preventDefault();

        var btnSubmit = document.getElementById('submitBtn');
        if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '⏳ ' + (getLang() === 'es' ? 'Publicando...' : 'Publishing...');
        }

        var form = e.target;
        var formData = new FormData(form);
        formData.append('ajax', '1');

        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(function(res) {
            return res.json();
        })
        .then(function(data) {
            if (data.success) {
                window.top.location.href = '/ascc/dashboard.php?success=producto_creado';
            } else {
                mostrarError(data.error || 'Error al publicar');
                if (btnSubmit) {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = '🚀 ' + (getLang() === 'es' ? 'Publicar' : 'Publish');
                }
            }
        })
        .catch(function(err) {
            console.error(err);
            mostrarError('Error de conexión');
            if (btnSubmit) {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '🚀 ' + (getLang() === 'es' ? 'Publicar' : 'Publish');
            }
        });
    }

    /* ══════════════════════════════════════════════════════
       DROPZONE DE IMÁGENES
    ══════════════════════════════════════════════════════ */

    function iniciarDropZone() {
        var dropArea = document.getElementById('dropArea');
        var fileInput = document.getElementById('imagenes');
        if (!dropArea || !fileInput) { return; }

        dropArea.addEventListener('click', function () { fileInput.click(); });

        dropArea.addEventListener('dragover', function (e) {
            e.preventDefault();
            dropArea.style.borderColor = '#10B981';
            dropArea.style.background = 'rgba(16,185,129,0.08)';
        });
        dropArea.addEventListener('dragleave', function () {
            dropArea.style.borderColor = '';
            dropArea.style.background = '';
        });
        dropArea.addEventListener('drop', function (e) {
            e.preventDefault();
            dropArea.style.borderColor = '';
            dropArea.style.background = '';
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                mostrarPreviews(e.dataTransfer.files);
            }
        });
        fileInput.addEventListener('change', function () { mostrarPreviews(fileInput.files); });
    }

    function mostrarPreviews(files) {
        var preview = document.getElementById('imagePreview');
        if (!preview) { return; }
        preview.innerHTML = '';
        var max = Math.min(files.length, 5);
        for (var i = 0; i < max; i++) {
            (function (file) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    var item = document.createElement('div');
                    item.className = 'image-preview-item';
                    item.innerHTML = '<img src="' + e.target.result + '" alt="Preview"><button class="remove-image" type="button">✕</button>';
                    item.querySelector('.remove-image').addEventListener('click', function () { item.remove(); });
                    preview.appendChild(item);
                };
                reader.readAsDataURL(file);
            })(files[i]);
        }
    }

    /* ══════════════════════════════════════════════════════
       CENTRAR MAPA EN UBICACIÓN
    ══════════════════════════════════════════════════════ */

    /* Coordenadas de las capitales de cada departamento (fallback sin API de Google) */
    var _DEPT_COORDS = {
        'Amazonas': { lat: -4.20, lng: -69.94 },
        'Antioquia': { lat: 6.25, lng: -75.56 },
        'Arauca': { lat: 7.09, lng: -70.76 },
        'Atlántico': { lat: 10.96, lng: -74.80 },
        'Bolívar': { lat: 10.39, lng: -75.51 },
        'Boyacá': { lat: 5.53, lng: -73.36 },
        'Caldas': { lat: 5.07, lng: -75.51 },
        'Caquetá': { lat: 1.61, lng: -75.59 },
        'Casanare': { lat: 5.34, lng: -72.39 },
        'Cauca': { lat: 2.44, lng: -76.61 },
        'Cesar': { lat: 10.46, lng: -73.25 },
        'Chocó': { lat: 5.69, lng: -76.65 },
        'Córdoba': { lat: 8.75, lng: -75.88 },
        'Cundinamarca': { lat: 4.71, lng: -74.07 },
        'Guainía': { lat: 3.86, lng: -67.93 },
        'Guaviare': { lat: 2.56, lng: -72.64 },
        'Huila': { lat: 2.93, lng: -75.28 },
        'La Guajira': { lat: 11.54, lng: -72.90 },
        'Magdalena': { lat: 11.24, lng: -74.20 },
        'Meta': { lat: 4.14, lng: -73.63 },
        'Nariño': { lat: 1.21, lng: -77.28 },
        'Norte de Santander': { lat: 7.88, lng: -72.50 },
        'Putumayo': { lat: 1.15, lng: -76.65 },
        'Quindío': { lat: 4.53, lng: -75.68 },
        'Risaralda': { lat: 4.81, lng: -75.69 },
        'San Andrés y Providencia': { lat: 12.53, lng: -81.72 },
        'Santander': { lat: 7.13, lng: -73.12 },
        'Sucre': { lat: 9.30, lng: -75.39 },
        'Tolima': { lat: 4.43, lng: -75.23 },
        'Valle del Cauca': { lat: 3.43, lng: -76.52 },
        'Vaupés': { lat: 1.25, lng: -70.25 },
        'Vichada': { lat: 4.42, lng: -70.92 }
    };

    function centrarMapaEnUbicacion(depto, mun, vereda) {
        if (!window.asccMapInstance || typeof google === 'undefined') { return; }

        var zoom = 9;
        if (mun && mun !== 'Otro (No aparece en la lista)') { zoom = 12; }
        if (vereda && vereda !== 'Otro (No está en la lista)') { zoom = 15; }

        function _moverMarcador(lat, lng) {
            var latLng = new google.maps.LatLng(lat, lng);
            window.asccMapInstance.setCenter(latLng);
            window.asccMapInstance.setZoom(zoom);
            if (window.asccMarkerInstance) {
                window.asccMarkerInstance.setPosition(latLng);
            } else {
                window.asccMarkerInstance = new google.maps.Marker({
                    position: latLng,
                    map: window.asccMapInstance,
                    draggable: true,
                    animation: google.maps.Animation.DROP,
                    title: 'Ubicación del producto'
                });
                window.asccMarkerInstance.addListener('dragend', function (ev) {
                    document.getElementById('lat').value = ev.latLng.lat();
                    document.getElementById('lng').value = ev.latLng.lng();
                });
            }
            document.getElementById('lat').value = lat;
            document.getElementById('lng').value = lng;
        }

        /* 1. Consultar coordenadas en nuestra propia BD (gratis, sin Geocoder) */
        var url = '/ascc/api/get_coordenadas.php?departamento=' + encodeURIComponent(depto);
        if (mun && mun !== 'Otro (No aparece en la lista)') {
            url += '&municipio=' + encodeURIComponent(mun);
        }
        if (vereda && vereda !== 'Otro (No está en la lista)') {
            url += '&vereda=' + encodeURIComponent(vereda);
        }

        /* Fallback con Google Geocoder cuando la BD no tiene coordenadas */
        function _geocodearConGoogle() {
            var partes = [];
            if (vereda && vereda !== 'Otro (No está en la lista)') { partes.push(vereda); }
            if (mun   && mun   !== 'Otro (No aparece en la lista)') { partes.push(mun); }
            partes.push(depto);
            partes.push('Colombia');
            new google.maps.Geocoder().geocode(
                { address: partes.join(', '), region: 'CO' },
                function (results, status) {
                    if (status === 'OK' && results[0]) {
                        var loc = results[0].geometry.location;
                        _moverMarcador(loc.lat(), loc.lng());
                    } else {
                        var coords = _DEPT_COORDS[depto];
                        if (coords) { _moverMarcador(coords.lat, coords.lng); }
                    }
                }
            );
        }

        fetch(url)
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (data && data.lat !== null && data.lng !== null) {
                    _moverMarcador(parseFloat(data.lat), parseFloat(data.lng));
                } else if (mun && mun !== 'Otro (No aparece en la lista)') {
                    /* BD sin coordenadas para este municipio/vereda → Google Geocoder */
                    _geocodearConGoogle();
                } else {
                    var coords = _DEPT_COORDS[depto];
                    if (coords) { _moverMarcador(coords.lat, coords.lng); }
                }
            })
            .catch(function () {
                if (mun && mun !== 'Otro (No aparece en la lista)') {
                    _geocodearConGoogle();
                } else {
                    var coords = _DEPT_COORDS[depto];
                    if (coords) { _moverMarcador(coords.lat, coords.lng); }
                }
            });
    }

    /* ══════════════════════════════════════════════════════
       AUTOCOMPLETE INTELIGENTE
       Muestra sugerencias mientras el campesino escribe,
       tolerante a errores ortográficos.
    ══════════════════════════════════════════════════════ */

    function crearContenedorSugerencias(inputId) {
        var existe = document.getElementById(inputId + '_sugerencias');
        if (existe) { return existe; }

        var contenedor = document.createElement('div');
        contenedor.id = inputId + '_sugerencias';
        contenedor.className = 'ascc-sugerencias';
        contenedor.style.display = 'none';

        var input = document.getElementById(inputId);
        if (input && input.parentNode) {
            input.parentNode.appendChild(contenedor);
        }

        return contenedor;
    }

    function mostrarSugerencias(inputId, sugerencias, onSeleccionar) {
        var contenedor = document.getElementById(inputId + '_sugerencias');
        if (!contenedor) { return; }

        if (!sugerencias || sugerencias.length === 0) {
            contenedor.style.display = 'none';
            contenedor.innerHTML = '';
            return;
        }

        var lang = getLang();
        var titulo = lang === 'es' ? '🔍 ¿Quisiste decir alguno de estos?' : '🔍 Did you mean one of these?';
        var html = '<div class="sugerencias-titulo">' + titulo + '</div>';

        sugerencias.forEach(function (sug) {
            html += '<div class="sugerencia-item" data-valor="' + sug.valor + '">' +
                '<span class="sugerencia-nombre">' + sug.valor + '</span>' +
                '<span class="sugerencia-pct">' + sug.similitud + '%</span>' +
                '</div>';
        });

        contenedor.innerHTML = html;
        contenedor.style.display = 'block';

        contenedor.querySelectorAll('.sugerencia-item').forEach(function (item) {
            item.addEventListener('click', function () {
                var valor = item.getAttribute('data-valor');
                var input = document.getElementById(inputId);
                if (input) { input.value = valor; }
                contenedor.style.display = 'none';
                contenedor.innerHTML = '';
                if (typeof onSeleccionar === 'function') { onSeleccionar(valor); }
            });
        });
    }

    function buscarSimilitudes(tipo, inputId, depto, municipio, onSeleccionar) {
        var input = document.getElementById(inputId);
        if (!input) { return; }

        var texto = input.value.trim();
        if (texto.length < 2) {
            var cont = document.getElementById(inputId + '_sugerencias');
            if (cont) { cont.style.display = 'none'; }
            return;
        }

        var url = '/ascc/api/buscar_similitud.php' +
            '?tipo=' + encodeURIComponent(tipo) +
            '&texto=' + encodeURIComponent(texto) +
            '&departamento=' + encodeURIComponent(depto);

        if (tipo === 'vereda' && municipio) {
            url += '&municipio=' + encodeURIComponent(municipio);
        }

        fetch(url)
            .then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.json(); })
            .then(function (sugerencias) { mostrarSugerencias(inputId, sugerencias, onSeleccionar); })
            .catch(function (err) { console.warn('[ASCC similitud]', err); });
    }

    function guardarUbicacionNueva(depto, municipio, vereda) {
        var body = { departamento: depto, municipio: municipio };
        if (vereda && vereda.trim() !== '') { body.vereda = vereda; }

        fetch('/ascc/api/guardar_ubicacion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                if (resp.ok && !resp.ya_existe) {
                    console.log('[ASCC] Nueva ubicación guardada:', resp.guardado);
                }
            })
            .catch(function (err) { console.warn('[ASCC guardar ubicación]', err); });
    }

    /* ══════════════════════════════════════════════════════
       CARGAR VEREDAS (función auxiliar)
    ══════════════════════════════════════════════════════ */

    function _cargarVeredas(depto, mun) {
        var lang = getLang();
        var veredaSelect = document.getElementById('vereda');
        if (!veredaSelect) { return; }

        veredaSelect.disabled = true;
        veredaSelect.innerHTML = '<option value="">' +
            (lang === 'en' ? 'Loading villages...' : 'Cargando veredas...') + '</option>';

        fetch('/ascc/api/get_veredas.php' +
            '?departamento=' + encodeURIComponent(depto) +
            '&municipio=' + encodeURIComponent(mun))
            .then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.json(); })
            .then(function (veredas) {
                veredaSelect.innerHTML = '<option value="">' +
                    (lang === 'en' ? 'Select a village' : 'Selecciona una vereda') + '</option>';

                if (Array.isArray(veredas)) {
                    veredas.forEach(function (ver) {
                        var opt = document.createElement('option');
                        opt.value = ver;
                        opt.textContent = ver;
                        if (ver === 'Otro (No está en la lista)') {
                            opt.style.fontWeight = 'bold';
                            opt.style.color = '#F59E0B';
                        }
                        veredaSelect.appendChild(opt);
                    });
                }
                veredaSelect.disabled = false;
            })
            .catch(function (err) {
                console.error('[ASCC veredas]', err);
                veredaSelect.innerHTML = '<option value="">Error cargando veredas</option>';
                veredaSelect.disabled = false;
            });
    }

    /* ══════════════════════════════════════════════════════
       SISTEMA DE UBICACIÓN INTELIGENTE
    ══════════════════════════════════════════════════════ */

    function iniciarUbicacion() {
        var deptoSelect = document.getElementById('departamento');
        var munSelect = document.getElementById('municipio');
        var veredaSelect = document.getElementById('vereda');
        if (!deptoSelect || !munSelect || !veredaSelect) { return; }

        var lang = getLang();

        /* ── Campo custom MUNICIPIO ──────────────────────── */
        if (!document.getElementById('municipio_custom_container')) {
            var mpioContainer = document.createElement('div');
            mpioContainer.id = 'municipio_custom_container';
            mpioContainer.className = 'form-group ubicacion-custom-container';
            mpioContainer.style.display = 'none';
            mpioContainer.innerHTML =
                '<label class="label-custom-ubicacion">✍️ ' +
                (lang === 'es' ? 'Escribe el nombre de tu municipio' : 'Write your municipality name') + ' *</label>' +
                '<div class="input-custom-wrap">' +
                '<input type="text" id="municipio_custom" class="input-custom-ubicacion" ' +
                'placeholder="' + (lang === 'es' ? 'Ej: El Guamal, Villa de Leyva...' : 'Ex: El Guamal, Villa de Leyva...') + '" autocomplete="off">' +
                '<span class="input-custom-icono">🔍</span>' +
                '</div>' +
                '<p class="hint-custom-ubicacion">' +
                (lang === 'es' ? '💡 Escribe despacio y te mostraremos sugerencias de municipios parecidos.' : '💡 Type slowly and we\'ll show similar municipality suggestions.') +
                '</p>' +
                '<button type="button" id="btn_guardar_municipio" class="btn-guardar-ubicacion" style="display:none">' +
                '💾 ' + (lang === 'es' ? 'Guardar este municipio' : 'Save this municipality') +
                '</button>' +
                '<div id="municipio_guardado_ok" class="ubicacion-guardada-ok" style="display:none">' +
                '✅ ' + (lang === 'es' ? '¡Municipio guardado! Ya puedes continuar.' : 'Municipality saved! You can continue.') +
                '</div>';
            munSelect.parentNode.appendChild(mpioContainer);
            crearContenedorSugerencias('municipio_custom');
        }

        /* ── Campo custom VEREDA ─────────────────────────── */
        if (!document.getElementById('vereda_custom_container')) {
            var veredaContainer = document.createElement('div');
            veredaContainer.id = 'vereda_custom_container';
            veredaContainer.className = 'form-group ubicacion-custom-container';
            veredaContainer.style.display = 'none';
            veredaContainer.innerHTML =
                '<label class="label-custom-ubicacion">✍️ ' +
                (lang === 'es' ? 'Escribe el nombre de tu vereda' : 'Write your village name') + ' *</label>' +
                '<div class="input-custom-wrap">' +
                '<input type="text" id="vereda_custom" class="input-custom-ubicacion" ' +
                'placeholder="' + (lang === 'es' ? 'Ej: La Chorrera, El Retiro...' : 'Ex: La Chorrera, El Retiro...') + '" autocomplete="off">' +
                '<span class="input-custom-icono">🔍</span>' +
                '</div>' +
                '<p class="hint-custom-ubicacion">' +
                (lang === 'es' ? '💡 Escribe despacio y te mostraremos si ya existe esa vereda.' : '💡 Type slowly and we\'ll check if that village already exists.') +
                '</p>' +
                '<button type="button" id="btn_guardar_vereda" class="btn-guardar-ubicacion" style="display:none">' +
                '💾 ' + (lang === 'es' ? 'Guardar esta vereda' : 'Save this village') +
                '</button>' +
                '<div id="vereda_guardada_ok" class="ubicacion-guardada-ok" style="display:none">' +
                '✅ ' + (lang === 'es' ? '¡Vereda guardada! Ya puedes continuar.' : 'Village saved! You can continue.') +
                '</div>';
            veredaSelect.parentNode.appendChild(veredaContainer);
            crearContenedorSugerencias('vereda_custom');
        }

        veredaSelect.innerHTML = '<option value="">' +
            (lang === 'en' ? 'First select Municipality' : 'Primero selecciona Municipio') + '</option>';

        /* ── Poblar departamentos desde datos estáticos ─── */
        if (typeof colombiaData !== 'undefined') {
            var deptos = Object.keys(colombiaData).sort();
            deptoSelect.innerHTML = '<option value="">' + (lang === 'en' ? 'Select' : 'Selecciona') + '</option>';
            deptos.forEach(function (d) {
                var opt = document.createElement('option');
                opt.value = d;
                opt.textContent = d;
                deptoSelect.appendChild(opt);
            });
        }

        /* ── Helper: llenar select de municipios ─────────── */
        function _llenarMunicipios(lista) {
            munSelect.innerHTML = '<option value="">' + (lang === 'en' ? 'Select municipality' : 'Selecciona un municipio') + '</option>';
            lista.forEach(function (mun) {
                var opt = document.createElement('option');
                opt.value = mun;
                opt.textContent = mun;
                if (mun === 'Otro (No aparece en la lista)') {
                    opt.style.fontWeight = 'bold';
                    opt.style.color = '#F59E0B';
                }
                munSelect.appendChild(opt);
            });
            munSelect.disabled = false;
        }

        /* ── Evento: DEPARTAMENTO ────────────────────────── */
        deptoSelect.addEventListener('change', function () {
            var depto = this.value;

            munSelect.innerHTML = '<option value="">' + (lang === 'en' ? 'Loading...' : 'Cargando...') + '</option>';
            veredaSelect.innerHTML = '<option value="">' + (lang === 'en' ? 'First select Municipality' : 'Primero selecciona Municipio') + '</option>';
            munSelect.disabled = true;
            veredaSelect.disabled = true;

            var mpCont = document.getElementById('municipio_custom_container');
            var vrCont = document.getElementById('vereda_custom_container');
            if (mpCont) { mpCont.style.display = 'none'; }
            if (vrCont) { vrCont.style.display = 'none'; }

            if (!depto) {
                munSelect.innerHTML = '<option value="">' + (lang === 'en' ? 'First select Department' : 'Primero selecciona Departamento') + '</option>';
                return;
            }

            /* Municipios desde datos estáticos (rápido, sin red) */
            var staticMpios = [];
            if (typeof colombiaData !== 'undefined' && colombiaData[depto]) {
                staticMpios = colombiaData[depto].municipios
                    .filter(function (m) { return m !== 'Otro'; });
            }

            /* Luego fusionar con los de la BD (usuarios que agregaron municipios) */
            fetch('/ascc/api/get_municipios.php?departamento=' + encodeURIComponent(depto))
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (dbMpios) {
                    var fusionados = staticMpios.slice();
                    if (Array.isArray(dbMpios)) {
                        dbMpios.forEach(function (m) {
                            if (m !== 'Otro (No aparece en la lista)' && fusionados.indexOf(m) === -1) {
                                fusionados.push(m);
                            }
                        });
                    }
                    fusionados.sort();
                    fusionados.push('Otro (No aparece en la lista)');
                    _llenarMunicipios(fusionados);
                })
                .catch(function () {
                    /* Si la BD falla, usar solo los estáticos */
                    var lista = staticMpios.slice().sort();
                    lista.push('Otro (No aparece en la lista)');
                    _llenarMunicipios(lista);
                });

            if (window.asccMapInstance && !window._gpsAutoFilling) { centrarMapaEnUbicacion(depto); }
        });

        /* ── Evento: MUNICIPIO ───────────────────────────── */
        munSelect.addEventListener('change', function () {
            var mun = this.value;
            var depto = deptoSelect.value;

            veredaSelect.innerHTML = '<option value="">' + (lang === 'en' ? 'Loading...' : 'Cargando...') + '</option>';
            veredaSelect.disabled = true;

            var vrCont = document.getElementById('vereda_custom_container');
            if (vrCont) { vrCont.style.display = 'none'; }

            var mpCont = document.getElementById('municipio_custom_container');
            var mpInput = document.getElementById('municipio_custom');

            if (mun === 'Otro (No aparece en la lista)') {
                if (mpCont) {
                    mpCont.style.display = 'block';
                    if (mpInput) {
                        mpInput.value = '';
                        mpInput.focus();

                        // Autocomplete con debounce de 500ms
                        mpInput.removeEventListener('input', mpInput._handler);
                        mpInput._handler = function () {
                            // Mostrar botón guardar cuando hay texto
                            var btnGuardar = document.getElementById('btn_guardar_municipio');
                            var okMsg = document.getElementById('municipio_guardado_ok');
                            if (btnGuardar) { btnGuardar.style.display = mpInput.value.trim().length >= 2 ? 'block' : 'none'; }
                            if (okMsg) { okMsg.style.display = 'none'; }

                            clearTimeout(_timerMunicipio);
                            _timerMunicipio = setTimeout(function () {
                                buscarSimilitudes('municipio', 'municipio_custom', depto, null,
                                    function (valorElegido) {
                                        // El usuario eligió una sugerencia → cargar veredas reales
                                        _cargarVeredas(depto, valorElegido);
                                        centrarMapaEnUbicacion(depto, valorElegido);
                                    }
                                );
                            }, 500);
                        };
                        mpInput.addEventListener('input', mpInput._handler);

                        // Botón guardar municipio
                        setTimeout(function () {
                            var btnGuardar = document.getElementById('btn_guardar_municipio');
                            if (btnGuardar && !btnGuardar._click) {
                                btnGuardar._click = true;
                                btnGuardar.addEventListener('click', function () {
                                    var valor = mpInput.value.trim();
                                    if (!valor) { return; }
                                    var okMsg = document.getElementById('municipio_guardado_ok');
                                    btnGuardar.disabled = true;
                                    btnGuardar.textContent = '⏳ ' + (getLang() === 'es' ? 'Guardando...' : 'Saving...');

                                    fetch('/ascc/api/guardar_ubicacion.php', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ departamento: deptoSelect.value, municipio: valor })
                                    })
                                    .then(function (r) { return r.json(); })
                                    .then(function () {
                                        /* Agregar nueva opción al select y auto-seleccionarla */
                                        var otroOpt = munSelect.querySelector('option[value="Otro (No aparece en la lista)"]');
                                        var existe = munSelect.querySelector('option[value="' + valor + '"]');
                                        if (!existe) {
                                            var optNew = document.createElement('option');
                                            optNew.value = valor;
                                            optNew.textContent = valor;
                                            munSelect.insertBefore(optNew, otroOpt || null);
                                        }
                                        munSelect.value = valor;

                                        /* Ocultar campo custom y mostrar ok */
                                        if (mpCont) { mpCont.style.display = 'none'; }
                                        if (okMsg) { okMsg.style.display = 'block'; }
                                        btnGuardar.style.display = 'none';
                                        btnGuardar.disabled = false;
                                        btnGuardar.textContent = '💾 ' + (getLang() === 'es' ? 'Guardar este municipio' : 'Save this municipality');

                                        /* Cargar veredas del nuevo municipio */
                                        _cargarVeredas(deptoSelect.value, valor);
                                        centrarMapaEnUbicacion(deptoSelect.value, valor);
                                    })
                                    .catch(function () {
                                        btnGuardar.disabled = false;
                                        btnGuardar.textContent = '💾 ' + (getLang() === 'es' ? 'Guardar este municipio' : 'Save this municipality');
                                    });
                                });
                            }
                        }, 100);

                        // Ocultar sugerencias al perder foco
                        mpInput.removeEventListener('blur', mpInput._blurHandler);
                        mpInput._blurHandler = function () {
                            setTimeout(function () {
                                var cont = document.getElementById('municipio_custom_sugerencias');
                                if (cont) { cont.style.display = 'none'; }
                            }, 300);
                        };
                        mpInput.addEventListener('blur', mpInput._blurHandler);
                    }
                }

                // Para "Otro municipio" solo se puede escribir la vereda también
                veredaSelect.innerHTML = '<option value="">' + (lang === 'en' ? 'Select a village' : 'Selecciona una vereda') + '</option>';
                var optOtro = document.createElement('option');
                optOtro.value = 'Otro (No está en la lista)';
                optOtro.textContent = 'Otro (No está en la lista)';
                optOtro.style.fontWeight = 'bold';
                optOtro.style.color = '#F59E0B';
                veredaSelect.appendChild(optOtro);
                veredaSelect.disabled = false;
                return;
            }

            if (mpCont) { mpCont.style.display = 'none'; }

            if (!mun) {
                veredaSelect.innerHTML = '<option value="">' + (lang === 'en' ? 'First select Municipality' : 'Primero selecciona Municipio') + '</option>';
                return;
            }

            _cargarVeredas(depto, mun);
            if (window.asccMapInstance && !window._gpsAutoFilling) { centrarMapaEnUbicacion(depto, mun); }
        });

        /* ── Evento: VEREDA ──────────────────────────────── */
        veredaSelect.addEventListener('change', function () {
            var vereda = this.value;
            var depto = deptoSelect.value;
            var mun = munSelect.value;

            var vrCont = document.getElementById('vereda_custom_container');
            var vrInput = document.getElementById('vereda_custom');

            if (vereda === 'Otro (No está en la lista)') {
                if (vrCont) {
                    vrCont.style.display = 'block';
                    if (vrInput) {
                        vrInput.value = '';
                        vrInput.focus();

                        // Botón rápido "Centro" encima del input
                        var btnCentro = document.getElementById('btn_vereda_centro');
                        if (!btnCentro) {
                            btnCentro = document.createElement('button');
                            btnCentro.type = 'button';
                            btnCentro.id = 'btn_vereda_centro';
                            btnCentro.className = 'btn-vereda-centro';
                            btnCentro.textContent = '📍 ' + (getLang() === 'es' ? 'Estoy en el Centro del municipio' : 'I\'m in the town center');
                            vrInput.parentNode.parentNode.insertBefore(btnCentro, vrInput.parentNode);
                            btnCentro.addEventListener('click', function () {
                                vrInput.value = 'Centro';
                                // Ocultar sugerencias
                                var cont = document.getElementById('vereda_custom_sugerencias');
                                if (cont) { cont.style.display = 'none'; }
                                // Mostrar botón guardar
                                var btnGuardar = document.getElementById('btn_guardar_vereda');
                                var okMsg = document.getElementById('vereda_guardada_ok');
                                if (btnGuardar) { btnGuardar.style.display = 'block'; }
                                if (okMsg) { okMsg.style.display = 'none'; }
                                // Centrar mapa
                                var mpioReal2 = (mun === 'Otro (No aparece en la lista)')
                                    ? (document.getElementById('municipio_custom') ? document.getElementById('municipio_custom').value.trim() : '')
                                    : mun;
                                centrarMapaEnUbicacion(deptoSelect.value, mpioReal2, 'Centro');
                            });
                        }
                        btnCentro.style.display = 'block';

                        vrInput.removeEventListener('input', vrInput._handler);
                        vrInput._handler = function () {
                            // Mostrar botón guardar cuando hay texto
                            var btnGuardar = document.getElementById('btn_guardar_vereda');
                            var okMsg = document.getElementById('vereda_guardada_ok');
                            if (btnGuardar) { btnGuardar.style.display = vrInput.value.trim().length >= 2 ? 'block' : 'none'; }
                            if (okMsg) { okMsg.style.display = 'none'; }

                            clearTimeout(_timerVereda);
                            _timerVereda = setTimeout(function () {
                                var mpioReal = (mun === 'Otro (No aparece en la lista)')
                                    ? (document.getElementById('municipio_custom') ? document.getElementById('municipio_custom').value.trim() : '')
                                    : mun;
                                buscarSimilitudes('vereda', 'vereda_custom', depto, mpioReal,
                                    function (valorElegido) {
                                        centrarMapaEnUbicacion(depto, mpioReal, valorElegido);
                                    }
                                );
                            }, 500);
                        };
                        vrInput.addEventListener('input', vrInput._handler);

                        // Botón guardar vereda
                        setTimeout(function () {
                            var btnGuardar = document.getElementById('btn_guardar_vereda');
                            if (btnGuardar && !btnGuardar._click) {
                                btnGuardar._click = true;
                                btnGuardar.addEventListener('click', function () {
                                    var valor = vrInput.value.trim();
                                    if (!valor) { return; }
                                    var mpioReal = (munSelect.value === 'Otro (No aparece en la lista)')
                                        ? (document.getElementById('municipio_custom') ? document.getElementById('municipio_custom').value.trim() : '')
                                        : munSelect.value;
                                    var okMsg = document.getElementById('vereda_guardada_ok');
                                    btnGuardar.disabled = true;
                                    btnGuardar.textContent = '⏳ ' + (getLang() === 'es' ? 'Guardando...' : 'Saving...');

                                    fetch('/ascc/api/guardar_ubicacion.php', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ departamento: deptoSelect.value, municipio: mpioReal, vereda: valor })
                                    })
                                    .then(function (r) { return r.json(); })
                                    .then(function () {
                                        /* Agregar nueva opción al select de veredas y auto-seleccionarla */
                                        var otroOpt = veredaSelect.querySelector('option[value="Otro (No está en la lista)"]');
                                        var existe = veredaSelect.querySelector('option[value="' + valor + '"]');
                                        if (!existe) {
                                            var optNew = document.createElement('option');
                                            optNew.value = valor;
                                            optNew.textContent = valor;
                                            veredaSelect.insertBefore(optNew, otroOpt || null);
                                        }
                                        veredaSelect.value = valor;

                                        /* Ocultar campo custom, botón centro y mostrar ok */
                                        if (vrCont) { vrCont.style.display = 'none'; }
                                        var btnCentroEl = document.getElementById('btn_vereda_centro');
                                        if (btnCentroEl) { btnCentroEl.style.display = 'none'; }
                                        if (okMsg) {
                                            okMsg.style.display = 'block';
                                            setTimeout(function () { okMsg.style.display = 'none'; }, 3000);
                                        }
                                        btnGuardar.style.display = 'none';
                                        btnGuardar.disabled = false;
                                        btnGuardar.textContent = '💾 ' + (getLang() === 'es' ? 'Guardar esta vereda' : 'Save this village');

                                        centrarMapaEnUbicacion(deptoSelect.value, mpioReal, valor);
                                    })
                                    .catch(function () {
                                        btnGuardar.disabled = false;
                                        btnGuardar.textContent = '💾 ' + (getLang() === 'es' ? 'Guardar esta vereda' : 'Save this village');
                                    });
                                });
                            }
                        }, 100);

                        vrInput.removeEventListener('blur', vrInput._blurHandler);
                        vrInput._blurHandler = function () {
                            setTimeout(function () {
                                var cont = document.getElementById('vereda_custom_sugerencias');
                                if (cont) { cont.style.display = 'none'; }
                            }, 300);
                        };
                        vrInput.addEventListener('blur', vrInput._blurHandler);
                    }
                }
            } else {
                if (vrCont) { vrCont.style.display = 'none'; }
                var btnCentroOcultar = document.getElementById('btn_vereda_centro');
                if (btnCentroOcultar) { btnCentroOcultar.style.display = 'none'; }
                if (window.asccMapInstance && depto && mun && vereda) {
                    centrarMapaEnUbicacion(depto, mun, vereda);
                }
            }
        });
    }

    /* ══════════════════════════════════════════════════════
       LLENAR REVISIÓN (PASO 7)
    ══════════════════════════════════════════════════════ */

    function llenarRevision() {
        var lang = getLang();

        var mpioCustom = document.getElementById('municipio_custom');
        var veredaCustom = document.getElementById('vereda_custom');
        var munSelect = document.getElementById('municipio');
        var veredaSel = document.getElementById('vereda');

        var municipioVal = munSelect ? munSelect.value : '';
        var veredaVal = veredaSel ? veredaSel.value : '';

        if (municipioVal === 'Otro (No aparece en la lista)' && mpioCustom) {
            municipioVal = mpioCustom.value.trim() || '(sin especificar)';
        }
        if (veredaVal === 'Otro (No está en la lista)' && veredaCustom) {
            veredaVal = veredaCustom.value.trim() || '(sin especificar)';
        }

        var resumen = {
            categoria: document.getElementById('categoria_principal') ? document.getElementById('categoria_principal').value : '',
            producto: document.getElementById('tipo_producto_display') ? document.getElementById('tipo_producto_display').value : '',
            descripcion: document.getElementById('descripcion') ? document.getElementById('descripcion').value : '',
            precio: document.getElementById('precio') ? document.getElementById('precio').value : '',
            cantidad: document.getElementById('cantidad') ? document.getElementById('cantidad').value : '',
            unidad: document.getElementById('unidad') ? document.getElementById('unidad').value : '',
            depto: document.getElementById('departamento') ? document.getElementById('departamento').value : '',
            municipio: municipioVal,
            vereda: veredaVal,
            lat: document.getElementById('lat') ? document.getElementById('lat').value : '',
            lng: document.getElementById('lng') ? document.getElementById('lng').value : ''
        };

        var imagenes = document.getElementById('imagenes');
        var numImagenes = (imagenes && imagenes.files) ? imagenes.files.length : 0;

        var unidades = {
            es: { unidad: 'Unidad', kg: 'Kilogramos', tonelada: 'Tonelada', bulto: 'Bulto', arroba: 'Arroba', litro: 'Litro', caja: 'Caja', docena: 'Docena' },
            en: { unidad: 'Unit', kg: 'Kilograms', tonelada: 'Ton', bulto: 'Bag', arroba: 'Arroba', litro: 'Liter', caja: 'Box', docena: 'Dozen' }
        };
        var unidadTexto = (unidades[lang] && unidades[lang][resumen.unidad]) ? unidades[lang][resumen.unidad] : resumen.unidad;

        var container = document.getElementById('revisionContainer');
        if (!container) { return; }

        var mapaTexto = (resumen.lat && resumen.lng)
            ? '📍 ' + parseFloat(resumen.lat).toFixed(4) + ', ' + parseFloat(resumen.lng).toFixed(4)
            : (lang === 'en' ? '⚠️ Not selected' : '⚠️ No seleccionado');

        var labels = {
            es: { categoria: 'Categoría', producto: 'Producto', descripcion: 'Descripción', precio: 'Precio', cantidad: 'Cantidad', unidad: 'Unidad', ubicacion: 'Ubicación', mapa: 'Coordenadas', imagenes: 'Imágenes', fotos: 'foto(s) cargada(s)' },
            en: { categoria: 'Category', producto: 'Product', descripcion: 'Description', precio: 'Price', cantidad: 'Quantity', unidad: 'Unit', ubicacion: 'Location', mapa: 'Coordinates', imagenes: 'Images', fotos: 'image(s) loaded' }
        };
        var L = labels[lang] || labels['es'];
        var catKey = resumen.categoria;
        var catNombre = (CATEGORIAS_NOMBRES[lang] && CATALOGO[catKey])
            ? (CATEGORIAS_NOMBRES[lang][CATALOGO[catKey].key] || resumen.categoria)
            : resumen.categoria;

        container.innerHTML =
            '<div class="revision-item"><span class="revision-label">🌿 ' + L.categoria + '</span><span class="revision-value">' + (CATALOGO[catKey] ? CATALOGO[catKey].icon + ' ' : '') + catNombre + '</span></div>' +
            '<div class="revision-item"><span class="revision-label">📦 ' + L.producto + '</span><span class="revision-value">' + resumen.producto + '</span></div>' +
            '<div class="revision-item revision-desc"><span class="revision-label">📝 ' + L.descripcion + '</span><span class="revision-value">' + resumen.descripcion + '</span></div>' +
            '<div class="revision-item"><span class="revision-label">💰 ' + L.precio + '</span><span class="revision-value">$' + resumen.precio + ' COP</span></div>' +
            '<div class="revision-item"><span class="revision-label">📊 ' + L.cantidad + '</span><span class="revision-value">' + resumen.cantidad + ' ' + unidadTexto + '</span></div>' +
            '<div class="revision-item"><span class="revision-label">📍 ' + L.ubicacion + '</span><span class="revision-value">' + resumen.depto + ' › ' + resumen.municipio + ' › ' + resumen.vereda + '</span></div>' +
            '<div class="revision-item"><span class="revision-label">🗺️ ' + L.mapa + '</span><span class="revision-value">' + mapaTexto + '</span></div>' +
            '<div class="revision-item"><span class="revision-label">📷 ' + L.imagenes + '</span><span class="revision-value">' + numImagenes + ' ' + L.fotos + '</span></div>';

        /* Al llegar al paso 7, guardar la ubicación nueva en BD
           SOLO si viene de campos "Otro" (no de selects normales) */
        var esMpioCustom = munSelect && munSelect.value === 'Otro (No aparece en la lista)';
        var esVeredaCustom = veredaSel && veredaSel.value === 'Otro (No está en la lista)';

        if (esMpioCustom || esVeredaCustom) {
            guardarUbicacionNueva(
                resumen.depto,
                resumen.municipio,
                esVeredaCustom ? resumen.vereda : ''
            );
        }
    }

    /* ══════════════════════════════════════════════════════
       INICIALIZAR MAPA
    ══════════════════════════════════════════════════════ */

    function iniciarMapa() {
        var mapDiv = document.getElementById('map');
        if (!mapDiv || typeof google === 'undefined') { return; }

        /* Límites geográficos de Colombia */
        var COLOMBIA_SW  = new google.maps.LatLng(-4.5, -79.5);
        var COLOMBIA_NE  = new google.maps.LatLng(13.0, -66.5);
        var colombiaBounds = new google.maps.LatLngBounds(COLOMBIA_SW, COLOMBIA_NE);

        var colombia = { lat: 4.5709, lng: -74.2973 };
        var map = new google.maps.Map(mapDiv, {
            center: colombia,
            zoom: 6,
            mapTypeId: 'roadmap',
            restriction: {
                latLngBounds: colombiaBounds,
                strictBounds: false
            },
            styles: [{ featureType: 'poi', stylers: [{ visibility: 'off' }] }]
        });

        window.asccMapInstance = map;
        window.asccMarkerInstance = null;

        /* Verificar si un punto está dentro del territorio colombiano */
        function dentroDeColombia(latLng) {
            var lat = latLng.lat();
            var lng = latLng.lng();
            return lat >= -4.5 && lat <= 13.0 && lng >= -79.5 && lng <= -66.5;
        }

        /* Mostrar aviso de fuera de Colombia */
        function mostrarErrorColombia() {
            var lang = getLang();
            var msg = lang === 'en'
                ? '🇨🇴 Only locations within Colombian territory are allowed.'
                : '🇨🇴 Solo se permiten ubicaciones dentro del territorio colombiano.';
            mostrarError(msg);
        }

        function colocarMarcador(latLng) {
            if (!dentroDeColombia(latLng)) {
                mostrarErrorColombia();
                return;
            }
            document.getElementById('lat').value = latLng.lat();
            document.getElementById('lng').value = latLng.lng();
            if (window.asccMarkerInstance) {
                window.asccMarkerInstance.setPosition(latLng);
            } else {
                window.asccMarkerInstance = new google.maps.Marker({
                    position: latLng,
                    map: map,
                    draggable: true,
                    animation: google.maps.Animation.DROP,
                    title: 'Ubicación del producto'
                });
                window.asccMarkerInstance.addListener('dragend', function (ev) {
                    if (!dentroDeColombia(ev.latLng)) {
                        mostrarErrorColombia();
                        /* Devolver el marcador a Colombia */
                        window.asccMarkerInstance.setPosition(colombia);
                        document.getElementById('lat').value = colombia.lat;
                        document.getElementById('lng').value = colombia.lng;
                        return;
                    }
                    document.getElementById('lat').value = ev.latLng.lat();
                    document.getElementById('lng').value = ev.latLng.lng();
                });
            }
        }

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    var userLatLng = new google.maps.LatLng(pos.coords.latitude, pos.coords.longitude);
                    var dentro = dentroDeColombia(userLatLng);
                    var centro = dentro ? userLatLng : new google.maps.LatLng(colombia.lat, colombia.lng);
                    map.setCenter(centro);
                    map.setZoom(dentro ? 15 : 6);
                    colocarMarcador(centro);

                    if (!dentro) { return; }

                    /* Reverse-geocoding: convertir coordenadas GPS en departamento/municipio
                       para auto-rellenar los selects sin que el usuario tenga que hacerlo */
                    var geocoder = new google.maps.Geocoder();
                    geocoder.geocode({ location: userLatLng }, function (results, status) {
                        if (status !== 'OK' || !results || !results[0]) { return; }

                        var deptoGPS = null;
                        var munGPS   = null;

                        results[0].address_components.forEach(function (comp) {
                            if (comp.types.indexOf('administrative_area_level_1') !== -1) {
                                deptoGPS = comp.long_name;
                            }
                            if (!munGPS && (comp.types.indexOf('locality') !== -1 ||
                                comp.types.indexOf('administrative_area_level_2') !== -1)) {
                                munGPS = comp.long_name;
                            }
                        });

                        if (!deptoGPS) { return; }

                        var deptoSelect = document.getElementById('departamento');
                        if (!deptoSelect) { return; }

                        /* Buscar coincidencia en el select de departamento */
                        var matchDepto = null;
                        for (var i = 0; i < deptoSelect.options.length; i++) {
                            var optD = deptoSelect.options[i].value;
                            if (!optD) { continue; }
                            if (optD.toLowerCase() === deptoGPS.toLowerCase() ||
                                deptoGPS.toLowerCase().indexOf(optD.toLowerCase()) !== -1 ||
                                optD.toLowerCase().indexOf(deptoGPS.toLowerCase()) !== -1) {
                                matchDepto = optD;
                                break;
                            }
                        }

                        if (!matchDepto) { return; }

                        /* Activar flag: las selecciones automáticas por GPS no deben
                           mover el mapa — ya está en la posición GPS exacta */
                        window._gpsAutoFilling = true;
                        deptoSelect.value = matchDepto;
                        deptoSelect.dispatchEvent(new Event('change'));

                        if (!munGPS) {
                            window._gpsAutoFilling = false;
                            return;
                        }

                        /* Polling: esperar a que el fetch de municipios termine (async)
                           y luego auto-seleccionar el municipio detectado por GPS */
                        var intentos = 0;
                        var buscarMunicipio = setInterval(function () {
                            var munSelect = document.getElementById('municipio');
                            intentos++;
                            if (!munSelect || intentos > 20) {
                                clearInterval(buscarMunicipio);
                                window._gpsAutoFilling = false;
                                return;
                            }
                            if (munSelect.disabled || munSelect.options.length <= 1) { return; }

                            var matchMun = null;
                            for (var j = 0; j < munSelect.options.length; j++) {
                                var optM = munSelect.options[j].value;
                                if (!optM || optM.indexOf('Otro') !== -1) { continue; }
                                if (optM.toLowerCase() === munGPS.toLowerCase() ||
                                    munGPS.toLowerCase().indexOf(optM.toLowerCase()) !== -1 ||
                                    optM.toLowerCase().indexOf(munGPS.toLowerCase()) !== -1) {
                                    matchMun = optM;
                                    break;
                                }
                            }

                            clearInterval(buscarMunicipio);
                            if (matchMun) {
                                munSelect.value = matchMun;
                                munSelect.dispatchEvent(new Event('change'));
                            }
                            /* Liberar flag: a partir de aquí cambios manuales mueven el mapa */
                            window._gpsAutoFilling = false;
                        }, 300);
                    });
                },
                function () { /* Sin permisos de geolocalización: Colombia centrada */ }
            );
        }

        map.addListener('click', function (e) { colocarMarcador(e.latLng); });
    }

    /* ══════════════════════════════════════════════════════
       INICIALIZACIÓN PRINCIPAL
    ══════════════════════════════════════════════════════ */

    function init() {
        console.log('[ASCC] Iniciando crear-producto.js v3 — Dropdown combinado catálogo + BD');

        renderCategories();
        iniciarDropZone();
        iniciarUbicacion();

        /* El mapa se inicializa lazy en _setStep() al llegar al paso 5,
           cuando el div #map ya es visible y tiene dimensiones reales. */

        var form = document.getElementById('productForm');
        if (form) {
            form.addEventListener('submit', validarFormularioCompleto);
        }

        /*
         * ── CORRECCIÓN DEL BUG nextStep ──────────────────────────
         * window.nextStep se define AQUÍ DENTRO de init().
         * El script está al final del body, entonces cuando el IIFE
         * se ejecuta, readyState ya es 'complete' e init() corre
         * sincrónicamente ANTES de llegar al final del IIFE.
         * Si pusiéramos window.nextStep = _nextStep; al final del IIFE,
         * esa línea se ejecutaría DESPUÉS de init() y destruiría los hooks.
         * Por eso window.nextStep SOLO se define aquí. ✓
         */
        window.nextStep = function () {
            // Al salir del paso 1 (categoría), renderizar los productos del paso 2
            if (currentStep === 1 && selectedCategory) {
                renderProducts();
            }
            // Al salir del paso 6 (imágenes), llenar la revisión del paso 7
            if (currentStep === 6) {
                llenarRevision();
            }
            _nextStep();
        };

        console.log('[ASCC] ✓ Inicialización completa');
    }

    /* ── Arrancar cuando el DOM esté listo ──────────────── */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // Script cargado al final del body: DOM ya está listo
        init();
    }

    /*
     * Exponer al scope global SOLO lo que necesitan los botones del HTML.
     * window.nextStep NO se expone aquí: ya está definido en init(). ✓
     * Si lo pusiéramos aquí sobreescribiría la versión con hooks.
     */
    window.prevStep = _prevStep;
    window.formatPriceCOP = formatPriceCOP;

}(window));