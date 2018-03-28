<?php declare(strict_types=1);

set_error_handler(function($no, $str, $file, $line) {
    throw new \ErrorException($str, $no, 1, $file, $line);
});

require(__DIR__.'/../vendor/autoload.php');
require(__DIR__.'/Domain.php');
require(__DIR__.'/Infra/Repository/Products.php');
require(__DIR__.'/Infra/WriteProjector.php');


$connection = new \pq\Connection;
$writer = new WriteProjector($connection);
$products = new Products($connection, $writer);

//$p = new Product([new Attribute(new Family('family1')), new Attribute(new Family('family1'))]);
//$products->add($p);
//
//$writer->commit();
//
//if(!empty($argv[1])) {
//    $product1 = $products->find($argv[1]);
//
//    $product1->attributes[0]->getFamily()->rename('AAAAAA');
//    $products->add($product1);
//    $writer->commit();
//
//    $product1 = $products->find($argv[1]);
//}

//for($i=0; $i < 10; $i++) {
//    $p = new Product([new Attribute(new Family('family1')), new Attribute(new Family('family1'))]);
//    $products->add($p);
//
//    ($i % 100 == 0) && $writer->commit();
//}
//$writer->commit();

foreach ($products->findAllEager() as $i => $product) {
    //current($product->attributes)->getFamily()->rename('BBBB');
    //$products->add($product);
    (iterator_to_array($product->attributes));
    //var_dump(iterator_to_array($product->attributes));
    //echo '----';var_dump(current($product->attributes)->getId());

    //($i % 100 == 0) && $writer->commit();
}
echo '------------', "\n";
foreach ($products->findAllLazy() as $i => $product) {
    var_dump(iterator_to_array($product->attributes));
    //current($product->attributes)->getFamily()->rename('BBBB');
    //$products->add($product);
    (iterator_to_array($product->attributes));
    //var_dump(iterator_to_array($product->attributes));
    //echo '----';var_dump(current($product->attributes)->getId());

    //($i % 100 == 0) && $writer->commit();
}
$writer->commit();
