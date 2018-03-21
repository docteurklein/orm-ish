<?php declare(strict_types=1);

namespace DocteurKlein\ORM;

set_error_handler(function($no, $str, $file, $line) {
    throw new \ErrorException($str, $no, 1, $file, $line);
});

require(__DIR__.'/src/Domain.php');
require(__DIR__.'/src/Infra.php');


$connection = new \pq\Connection;
$uow = new PQWriteProjection($connection, [
    FamilyCreated::class => [
        function($connection) {
            return $connection->prepare('familycreated', 'insert into family values($1, $2)');
        },
        function($statement, $event) {
            $statement->exec([
                $event->id, $event->name
            ]);
        }
    ],
    FamilyRenamed::class => [
        function($connection) {
            return $connection->prepare('familyrenamed', 'update family set name = $1 where family_id = $2');
        },
        function($statement, $event) {
            $statement->exec([$event->name, $event->id]);
        }
    ],
    ProductCreated::class => [
        function($connection) {
            return $connection->prepare('productcreated', 'insert into product values($1)');
        },
        function($statement, $event) {
            $statement->exec([$event->id]);
        }
    ],
    AttributeCreated::class => [
        function($connection) {
            return $connection->prepare('attributecreated', 'insert into attribute values($1, $2)');
        },
        function($statement, $event) {
            $statement->exec([$event->id, $event->family->getId()]);
        }
    ],
    AttributeAddedToProduct::class => [
        function($connection) {
            return $connection->prepare('attributeaddedtoproduct', 'insert into product_attribute values($1, $2)');
        },
        function($statement, $event) {
            $statement->exec([$event->product->getId(), $event->attribute->getId()]);
        }
    ],
]);
$products = new Products($connection, $uow);

//$p = new Product([new Attribute(new Family('family1')), new Attribute(new Family('family1'))]);
//$products->add($p);
//
//$uow->commit();
//
//if(!empty($argv[1])) {
//    $product1 = $products->find($argv[1]);
//
//    $product1->attributes[0]->getFamily()->rename('AAAAAA');
//    $products->add($product1);
//    $uow->commit();
//
//    $product1 = $products->find($argv[1]);
//}

//for($i=0; $i < 10000; $i++) {
//    $p = new Product([new Attribute(new Family('family1')), new Attribute(new Family('family1'))]);
//    $products->add($p);
//
//    ($i % 100 == 0) && $uow->commit();
//}
//$uow->commit();

foreach ($products->findAll() as $i => $product) {
    $product->attributes[0]->getFamily()->rename('BBBB');
    $products->add($product);
    var_dump($product->getId());

    ($i % 100 == 0) && $uow->commit();
}
$uow->commit();
