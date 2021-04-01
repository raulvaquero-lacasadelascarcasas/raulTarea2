<?php

/**
 * 2007-2021 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2021 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Rv_importarcsv extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'rv_importarcsv';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Raul Vaquero';
        $this->need_instance = 1;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Importar CSV');
        $this->description = $this->l('importar archivos csv');
        $this->confirmUninstall = $this->l('');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        Configuration::updateValue('RV_IMPORTARCSV_LIVE_MODE', false);

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader');
    }

    public function uninstall()
    {
        Configuration::deleteByName('RV_IMPORTARCSV_LIVE_MODE');

        return parent::uninstall();
    }

    public function getContent()
    {
       
        return $this->postProcess() . $this->getForm();
    }

    public function getForm()
    {
        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->identifier = $this->identifier;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->languages = $this->context->controller->getLanguages();
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = $this->context->controller->default_form_language;
        $helper->allow_employee_form_lang = $this->context->controller->allow_employee_form_lang;
        $helper->title = $this->displayName;
        $helper->submit_action = 'importar';
        $helper->fields_value['texto_header'] = Configuration::get('HOLI_MODULO_TEXTO_HOME');
        $helper->fields_value['texto_footer'] = Configuration::get('HOLI_MODULO_TEXTO_FOOTER');


        $this->form[0] = array(
            'form' => array(
                'legend' => array(
                   
                    'title' => $this->l('Importar Archivos CSV ')
                ),
                'input' => array(
                    array(
                        'type' => 'file',
                        'label' => $this->l('Fichero csv'),
                        'desc' => $this->l('Formato: Nombre,Referencia,EAN13,Precio de coste,Precio de venta,IVA,Cantidad,Categorias,Marca'),
                        'hint' => $this->l('Fichero csv '),
                        'name' => 'archivo',
                        'accept' => '.csv',
                        'lang' => false,
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save')
                )
            )
        );

        return $helper->generateForm($this->form);
    }

    protected function getConfigFormValues()
    {
        return array(
            'RV_IMPORTARCSV_LIVE_MODE' => Configuration::get('RV_IMPORTARCSV_LIVE_MODE', true),
            'RV_IMPORTARCSV_ACCOUNT_EMAIL' => Configuration::get('RV_IMPORTARCSV_ACCOUNT_EMAIL', 'contact@prestashop.com'),
            'RV_IMPORTARCSV_ACCOUNT_PASSWORD' => Configuration::get('RV_IMPORTARCSV_ACCOUNT_PASSWORD', null),
        );
    }

    protected function postProcess()
    {
        if (Tools::isSubmit('importar')) {

            $this->parsearCsv($_FILES['archivo']);
            return $this->displayConfirmation($this->l('Se ha importado correctamente'));
        }
    }

    public function readCSV($csvFile)
    {
        $file_handle = fopen($csvFile, 'r');
        $line_of_text = array();
        while (!feof($file_handle)) {
            $line_of_text[] = fgetcsv($file_handle, 0, ',');
        }
        fclose($file_handle);
        return $line_of_text;
    }

    public function parsearCsv($adjunto)
    {
        $csv = $this->readCSV($adjunto['tmp_name']);
        $totRows = count($csv);

        if ($totRows < 2) {
            $this->controller->errors[] = $this->l('Formato de excel erroneo, comprueba las filas.');
            return false;
        }
        //$csvTitles = array();
        $csvContent = array();
        $obligatorias = array('nombre', 'referencia', 'ean13', 'precio de coste', 'precio de venta', 'iva', 'cantidad', 'categorias', 'marca');

        $separado_por_comas = strtolower(implode(",", $csv[0]));
        //echo $separado_por_comas;
        $csvTitles = explode(",", $separado_por_comas);
        /*print_r($junto_de_nuevo);*/
        $intersect = count(array_intersect($obligatorias, $csvTitles));

        if ($intersect != count($obligatorias)) {
            $this->controller->errors[] = $this->l('Faltan columnas obligatorias');
            return false;
        }
        //Create associative array with titles for each row
        array_shift($csv);
        $idxRow = 0;
        foreach ($csv as $row) {
            $idxCol = 0;
            if (!empty($row)) {
                foreach ($row as $col) {
                    $this->l('Faltan columnas obligatorias');
                    $csvContent[$idxRow][$csvTitles[$idxCol]] = $col;
                    $idxCol++;
                }
            }
            $idxRow++;
        }

        $this->desmigarCSV($csvContent);
    }

    public function desmigarCSV($csv)
    {
        $limite = count($csv);
        for ($i = 0; $i < $limite; $i++) {
            //lo declaro aquí porque necesito limpiarlo
            $id_categoria = [];
            // $id_categoria[]=$this->crearCategoria($csv[$i]['categorias']);
            $id_categoria[] = $this->crearCategoria($csv[$i]['categorias']);
            $id_fabricante = $this->crearFabricante($csv[$i]['marca']);
            $nombre = $csv[$i]['nombre'];
            $referencia = $csv[$i]['referencia'];
            $ean13 = $csv[$i]['ean13'];
            $preciocoste = $csv[$i]['precio de coste'];
            $precioventa = $csv[$i]['precio de venta'];
            $iva = $csv[$i]['iva'];
            $cantidad = $csv[$i]['cantidad'];

            $this->crearproducto($id_categoria, $id_fabricante, $nombre, $referencia, $ean13, $preciocoste, $precioventa, $iva, $cantidad);
        }
        return $this->displayConfirmation($this->l('Updated Successfully'));
    }

    public function crearproducto($id_categoria, $id_fabricante, $nombre, $referencia, $ean13, $preciocoste, $precioventa, $iva, $cantidad)
    {

        $product = new Product();
        $product->reference = $referencia;
        $product->name =  array((int)(Configuration::get('PS_LANG_DEFAULT')) => $nombre);
        $product->link_rewrite = array((int)Configuration::get('PS_LANG_DEFAULT') =>  Tools::str2url($nombre));
        $product->description_short = array((int)(Configuration::get('PS_LANG_DEFAULT')) => " ");  //  unicode
        $product->description = array((int)(Configuration::get('PS_LANG_DEFAULT')) => " "); // unicode
        $product->id_category_default = $id_categoria[0][0];
        $product->redirect_type = '404';
        $product->minimal_quantity = 1;
       
        $product->show_price = 1;
        $product->on_sale = 0;
        $product->online_only = 1;
        $product->ecotax = '0.000000';
        $product->price =  Validate::isPrice($precioventa);
        $product->wholesale_price = $preciocoste; // '0.000000';
        $product->ean13 = $ean13;
        $product->id_manufacturer = $id_fabricante;
        $product->Add();
        //una vez lo he metido, actualizo las categorías del producto, tenía que seleccionar que posición del array meter(en este caso hace un array de 0 y ahí mete los elementos)
        $product->updateCategories($id_categoria[0]);
        StockAvailable::setQuantity($product->id, null, $cantidad); // Si no tiene un id_product_attribute, déjelo como "nulo". 
        //$product->updateCategories(array('74','75','76'));

        /* $product->update();*/
    }

    public function crearCategoria($datoscat)
    {
        $home = (int)Configuration::get('PS_HOME_CATEGORY');
        //Cambio el string de ; a , 
        $nombres = str_replace(";", ",", $datoscat);
        //Después lo paso a un array xd
        $nombrearray = explode(",", $nombres);
        $arraydecategorias = array();
        $limite = count($nombrearray);
        //echo $limite;
        for ($i = 0; $i < $limite; $i++) {
            $category = new Category();
            $category->name = array((int)(Configuration::get('PS_LANG_DEFAULT')) =>  $nombrearray[$i]);
            $category->link_rewrite = array((int)Configuration::get('PS_LANG_DEFAULT') =>  Tools::str2url($nombrearray[$i]));
            $category->description_short = array((int)(Configuration::get('PS_LANG_DEFAULT')) => "");  //  unicode
            $category->description = array((int)(Configuration::get('PS_LANG_DEFAULT')) => ""); // unicode
            $category->id_parent = $home; // Para que las puedas visualizar,primero tienen que ser hijas del primer elemento raiz tiene que ser un 2
            $category->active = 1;

            if (!$id_category = Db::getInstance()->getValue('SELECT id_category FROM ' . _DB_PREFIX_ . 'category_lang WHERE name in("' . pSQL($nombrearray[$i]) . '")')) {

                $category->add();
                array_push($arraydecategorias, $category->id);
            } else {

                array_push($arraydecategorias, $id_category);
            }

        } 


        return $arraydecategorias;
    }

    public function crearFabricante($nombre)
    {
        $manucfacturer = new Manufacturer();
        $manucfacturer->name = Validate::isCatalogName($nombre);
        $manucfacturer->name = $nombre;
        $manucfacturer->active = 1;
        $manucfacturer->description = '';
        $manucfacturer->short_description = '';
        if (!$id_manufacturer = Db::getInstance()->getValue('SELECT id_manufacturer FROM ' . _DB_PREFIX_ . 'manufacturer WHERE name="' . pSQL($nombre) . '"')) {
            $manucfacturer->add();
            return $manucfacturer->id;
        } else {
            
            return $id_manufacturer;
            //sea falso o no, retorna el ultimo id que ha conseguido
        }
    }


}
