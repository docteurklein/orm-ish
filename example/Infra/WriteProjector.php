<?php declare(strict_types=1);

final class WriteProjector
{
    private $connection;
    private $streams = [];

    public function __construct(\pq\Connection $connection)
    {
        $this->connection = $connection;
    }

    public function addStream(iterable $stream): void
    {
        if ($stream instanceof \Iterator && !$stream->valid()) {
            return;
        }
        if (is_array($stream) && empty($stream)) {
            return;
        }
        $this->streams[] = $stream;
    }

    public function commit(): void
    {
        if (empty($this->streams)) {
            return;
        }

        $transaction = $this->connection->startTransaction();

        $transaction->connection->exec('SET CONSTRAINTS attribute_family_id_fkey DEFERRED');
        $transaction->connection->exec('SET CONSTRAINTS product_attribute_attribute_id_fkey DEFERRED');

        $createFamily = $this->connection->prepare('createFamily', 'insert into family values($1, $2)');
        $renameFamily = $this->connection->prepare('renameFamiliy', 'update family set name = $1 where family_id = $2');
        $createProduct = $this->connection->prepare('createProduct', 'insert into product values($1)');
        $createAttribute = $this->connection->prepare('createAttribute', 'insert into attribute values($1, $2)');
        $associateAttributeToProduct = $this->connection->prepare('associateAttributeToProduct', 'insert into product_attribute values($1, $2)');

        foreach($this->streams as $stream) {
            foreach ($stream as $event) {
                switch (true) {
                    case $event instanceof ProductCreated:
                        $createProduct->exec([$event->id]);
                        break;
                    case $event instanceof FamilyCreated:
                        $createFamily->exec([$event->id, $event->name]);
                        break;
                    case $event instanceof AttributeCreated:
                        $createAttribute->exec([$event->id, $event->family->getId()]);
                        break;
                    case $event instanceof AttributeAddedToProduct:
                        $associateAttributeToProduct->exec([$event->product->getId(), $event->attribute->getId()]);
                        break;
                    case $event instanceof FamilyRenamed:
                        $renameAttribute->exec([$event->id, $event->name]);
                        break;
                    case $event instanceof EntityChanged:
                    case is_object($event):
                        $eventName = strtolower((new \ReflectionObject($event))->getShortName());
                        $transaction->connection->notify($eventName, json_encode($event));
                        break;
                    default:
                        break;
                }
            }
        }

        $transaction->commit();
        $this->streams = [];
    }
}
