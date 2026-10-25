<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Catálogo de ejemplo. Repetible: actualiza por SKU sin duplicar.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['TEC-001', 'Teclado mecánico TKL', 'Teclado mecánico sin teclado numérico, switches rojos y retroiluminación RGB.', '79.90', 40, true],
            ['TEC-002', 'Mouse inalámbrico ergonómico', 'Mouse vertical inalámbrico de 2.4 GHz con 6 botones programables.', '34.50', 65, true],
            ['TEC-003', 'Monitor 27 pulgadas QHD', 'Monitor IPS de 27 pulgadas, resolución 2560x1440 a 144 Hz.', '289.00', 15, true],
            ['TEC-004', 'Audífonos con cancelación de ruido', 'Audífonos Bluetooth over-ear con 30 horas de batería.', '149.99', 25, true],
            ['TEC-005', 'Webcam Full HD', 'Cámara web 1080p con micrófono estéreo y corrección de luz.', '49.00', 50, true],
            ['TEC-006', 'SSD NVMe 1 TB', 'Unidad de estado sólido PCIe 4.0 con lectura de hasta 7000 MB/s.', '89.95', 30, true],
            ['TEC-007', 'Hub USB-C 7 en 1', 'Adaptador con HDMI 4K, lector SD, 3 puertos USB-A y carga PD de 100 W.', '39.90', 80, true],
            ['TEC-008', 'Laptop 14 pulgadas ultraligera', 'Portátil de 1.2 kg con 16 GB de RAM y 512 GB SSD.', '899.00', 8, true],
            ['HOG-001', 'Cafetera de goteo programable', 'Cafetera de 12 tazas con temporizador y jarra térmica.', '59.99', 20, true],
            ['HOG-002', 'Lámpara LED de escritorio', 'Lámpara regulable con 5 temperaturas de color y puerto USB.', '27.50', 45, true],
            ['HOG-003', 'Silla de oficina ergonómica', 'Silla con soporte lumbar ajustable y apoyabrazos 4D.', '219.00', 12, true],
            ['HOG-004', 'Botella térmica 750 ml', 'Botella de acero inoxidable que mantiene la temperatura 24 horas.', '18.75', 120, true],
            ['DEP-001', 'Mochila urbana impermeable', 'Mochila de 25 L con compartimento acolchado para laptop de 15 pulgadas.', '45.00', 35, true],
            ['DEP-002', 'Set de bandas de resistencia', 'Set de 5 bandas de látex con distintos niveles de resistencia.', '22.90', 60, true],
            ['DEP-003', 'Reloj deportivo con GPS', 'Reloj con monitor de frecuencia cardíaca y 14 días de batería.', '179.00', 10, true],
            ['LIB-001', 'Libro: Código limpio', 'Guía clásica sobre buenas prácticas de programación.', '32.00', 25, true],
            ['LIB-002', 'Libro: Diseño de APIs REST', 'Principios de diseño, versionado y seguridad de APIs.', '28.50', 18, true],
            ['ACC-001', 'Cargador GaN 65 W', 'Cargador compacto con dos puertos USB-C y uno USB-A.', '42.00', 55, true],
            ['ACC-002', 'Funda para laptop 14 pulgadas', 'Funda de neopreno con bolsillo frontal para accesorios.', '19.90', 70, true],
            ['ACC-003', 'Soporte ajustable para laptop', 'Soporte de aluminio plegable con 6 niveles de altura (agotado).', '29.99', 0, true],
            ['OLD-001', 'Reproductor MP3 (descontinuado)', 'Producto inactivo: visible solo para administradores.', '15.00', 3, false],
        ];

        foreach ($products as [$sku, $name, $description, $price, $stock, $active]) {
            $product = Product::withTrashed()->firstOrNew(['sku' => $sku]);
            $product->fill([
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'stock' => $stock,
                'is_active' => $active,
            ]);
            $product->deleted_at = null;
            $product->save();
        }

        $this->command?->info(count($products).' productos de ejemplo sincronizados.');
    }
}
