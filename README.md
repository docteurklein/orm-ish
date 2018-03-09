# ORM POC

    docker build -t orm .
    docker run --rm --net host -u $UID:$UID -v $PWD:$PWD -w $PWD orm php test.php
