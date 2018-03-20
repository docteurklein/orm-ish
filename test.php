<?php declare(strict_types=1);

namespace DocteurKlein\ORM;

set_error_handler(function($no, $str, $file, $line) {
    throw new \ErrorException($str, $no, 1, $file, $line);
});

$connection = new \pq\Connection;

require(__DIR__.'/src/Domain.php');
require(__DIR__.'/src/Infra.php');


$uow = new WriteProjection($connection);
$products = new Products($connection, $uow);

$p = new Product([new Attribute(new Family('family1')), new Attribute(new Family('family1'))]);
$products->add($p);

$uow->commit();

if(!empty($argv[1])) {
    $product1 = $products->find($argv[1]);
    var_dump(iterator_to_array($product1->attributes));
    var_dump(iterator_to_array($product1->attributes));

    $product1->attributes[0]->getFamily()->rename('AAAAAA');
    $products->add($product1);
    $uow->commit();

    $product1 = $products->find($argv[1]);
    var_dump($product1);
}

for($i=0; $i < 10; $i++) {
    $p = new Product([new Attribute(new Family('family1')), new Attribute(new Family('family1'))]);
    $products->add($p);

    ($i % 100 == 0) && $uow->commit();
}
$uow->commit();

//foreach ($products->findAll() as $product) {
//    var_dump(iterator_to_array($product->attributes));
//}
