<?php declare(strict_types=1);

use DocteurKlein\ORMish\MemoizedGenerator;

final class Products
{
    private $writer;
    private $connection;

    public function __construct(\pq\Connection $connection, WriteProjector $writer)
    {
        $this->connection = $connection;
        $this->writer = $writer;
    }

    public function find($id): Product
    {
        //$resultSet = $this->getLazyResult($id);
        $resultSet = $this->getEagerNestedResult($id);

        return Product::fromResultSet($resultSet);
    }

    public function findAllLazy(): iterable
    {
        $result = $this->connection->execParams(<<<SQL
            select product.*
            from product
            order by product_id
            limit 5
SQL
        , []);

        while ($row = $result->fetchRow(\pq\Result::FETCH_ASSOC)) {
            $row['attributes'] = $this->lazyLoadAttributes($row['product_id']);
            yield Product::fromResultSet($row);
        }
    }

    public function findAllEager()
    {
        $result = $this->connection->execParams(<<<SQL
            select product.*, jsonb_agg(attribute.*) attributes
            from product
            left join product_attribute using (product_id)
            left join (select attribute.*, row_to_json(f.*) as family from attribute inner join family f using(family_id)) attribute using (attribute_id)
            group by product.product_id
            order by product_id
            limit 5
SQL
        , []);

        while ($row = $result->fetchRow(\pq\Result::FETCH_ASSOC)) {
            $row['attributes'] = new \ArrayIterator(array_map([Attribute::class, 'fromResultSet'], $row['attributes']));
            yield Product::fromResultSet($row);
        }
    }

    private function getLazyResult($id)
    {
        $result = $this->connection->execParams(<<<SQL
            select product.*
            from product
            where product.product_id = $1
SQL
        , [$id]);
        $resultSet = $result->fetchRow(\pq\Result::FETCH_ASSOC);
        $resultSet['attributes'] = $this->lazyLoadAttributes($resultSet['product_id']);

        return $resultSet;
    }

    private function lazyLoadAttributes($productId)
    {
        return new MemoizedGenerator((function($productId) {
            $result = $this->connection->execParams(<<<SQL
                select attribute.*, row_to_json(f.*) as family
                from attribute
                inner join family f using(family_id)
                inner join product_attribute using(attribute_id)
                where product_id = $1
SQL
            , [$productId]);

            while ($row = $result->fetchRow(\pq\Result::FETCH_ASSOC)) {
                yield Attribute::fromResultSet($row);
            }
        })($productId));
    }

    private function getEagerNestedResult($id)
    {
        $result = $this->connection->execParams(<<<SQL
            select product.*, jsonb_agg(attribute.*) attributes
            from product
            left join product_attribute using (product_id)
            left join (select attribute.*, row_to_json(f.*) as family from attribute inner join family f using(family_id)) attribute using (attribute_id)
            where product.product_id = $1
            group by product.product_id
SQL
        , [$id]);
        $resultSet = $result->fetchRow(\pq\Result::FETCH_ASSOC);
        $resultSet['attributes'] = array_map([Attribute::class, 'fromResultSet'], $resultSet['attributes']);

        return $resultSet;
    }

    public function add(Product $product): void
    {
        $this->writer->addStream($product->popEvents());
    }
}
