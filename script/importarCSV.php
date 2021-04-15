  <?php
include(dirname(__FILE__) . '/../config/config.inc.php');
include(dirname(__FILE__) . '/../init.php');
$archivo = fopen('products.csv', 'r');
$csv = array();
while (!feof($archivo)) {
    $csv[] = fgetcsv($archivo, 0, ',');
}
fclose($archivo);
unset($csv[0]);
$id_lang = Context::getContext()->language->id;

foreach ($csv as $valor) {
    $preciocoste = (float)$valor[3];
    $precioventa = (float)$valor[4];
    $cantidad = (int)$valor[6];
    $product = new Product();
    $product->name =  createMultiLangField($valor[0]);
    $product->reference =  $valor[1];
    $product->ean13 = $valor[2];
    $product->wholesale_price = $preciocoste;
    $product->price =  $precioventa;
    $product->link_rewrite = $valor[1];
    $nombreCategoria = $valor[7];
    $nombrearray = explode(";", $nombreCategoria);
    $array_categoria = [];
    array_push($array_categoria, Configuration::get('PS_HOME_CATEGORY'));

    foreach ($nombrearray as $valor) {
        $categoria = Category::searchByName($id_lang, $valor);
        if (count($categoria) < 1) {
            $category = new Category();
            $category->name = createMultiLangField($valor);
            $link_rewrite = Tools::link_rewrite($category->name[$id_lang]);
            $category->link_rewrite = createMultiLangField($link_rewrite);
            $category->active = 1;
            $category->id_parent = Configuration::get('PS_HOME_CATEGORY');
            $category->add();
            array_push($array_categoria, $category->id);
        } else {
            array_push($array_categoria, $categoria[0]['id_category']);
        }
    }

    $product->id_category_default = $array_categoria[0];
    $product->add();
    $product->updateCategories($array_categoria);
    StockAvailable::setQuantity($product->id, 0, $cantidad);
}
 
function createMultiLangField($field) {
    $res = [];
    foreach (Language::getIDs(false) as $id_lang) {
        $res[$id_lang] = $field;
    }
    return $res;
}
