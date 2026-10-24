<?php

declare(strict_types=1);

use AnthonyMartin\GeoLocation\{BoundingBox, Circle, GeoPoint, Polygon};
use AnthonyMartin\GeoLocation\Sql\{Dialect, MySqlDialect, NearbyQuery, PostgresDialect, SqliteDialect};
use AnthonyMartin\GeoLocation\Exceptions\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SqlTest extends TestCase {
    public function testQuotedAndBoundBoxFragments(): void {
        foreach ([new MySqlDialect(),new PostgresDialect(),new SqliteDialect()] as $dialect) {
            $q = new NearbyQuery($dialect,latColumn:'p.lat',lonColumn:'p.lng');
            $f = $q->within(new BoundingBox(-1,170,1,-170));
            $lat=$dialect->quoteIdentifier('p.lat');$lon=$dialect->quoteIdentifier('p.lng');
            self::assertSame("(($lat BETWEEN (? + 0.0) AND (? + 0.0)) AND ($lon >= (? + 0.0) OR $lon <= (? + 0.0)))",$f->sql());
            self::assertSame([-1.0,1.0,170.0,-170.0],$f->bindings());
            self::assertFalse($f->requiresPostFiltering());
        }
        self::assertSame('`p`.`lat`',(new MySqlDialect())->quoteIdentifier('p.lat'));
        self::assertSame('"p"."lat"',(new PostgresDialect())->quoteIdentifier('p.lat'));
        foreach (['lat; DROP TABLE places','lat --','lat`','lat"','p.*','lat()',''] as $column) {
            try {new NearbyQuery(new SqliteDialect(),latColumn:$column);self::fail('Unsafe identifier accepted');}
            catch (InvalidArgumentException) {self::assertTrue(true);}
        }
    }

    public function testSqliteExecutionMatchesPhpContainment(): void {
        $this->executeCases(new PDO('sqlite::memory:'),new SqliteDialect());
    }

    public function testMySqlExecutionWhenConfigured(): void {
        $dsn=getenv('GEOLOCATION_MYSQL_DSN');
        if (!$dsn) {self::markTestSkipped('Set GEOLOCATION_MYSQL_DSN and optional GEOLOCATION_DB_USER/PASSWORD.');}
        $this->executeCases(new PDO($dsn,getenv('GEOLOCATION_DB_USER')?:null,getenv('GEOLOCATION_DB_PASSWORD')?:null),new MySqlDialect());
    }

    public function testPostgresExecutionWhenConfigured(): void {
        $dsn=getenv('GEOLOCATION_POSTGRES_DSN');
        if (!$dsn) {self::markTestSkipped('Set GEOLOCATION_POSTGRES_DSN and optional GEOLOCATION_DB_USER/PASSWORD.');}
        $this->executeCases(new PDO($dsn,getenv('GEOLOCATION_DB_USER')?:null,getenv('GEOLOCATION_DB_PASSWORD')?:null),new PostgresDialect());
    }

    private function executeCases(PDO $db, Dialect $dialect): void {
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $db->exec('CREATE TEMPORARY TABLE places (id INTEGER PRIMARY KEY, lat DOUBLE PRECISION, lng DOUBLE PRECISION)');
        $coordinates=[[0,0],[.1,.1],[.4,.4],[1,1],[-1,-1],[0,179.9],[0,-179.9],[0,170],[0,-170],[0,180],[0,-180],[89.9,20],[90,180]];
        $insert=$db->prepare('INSERT INTO places VALUES (?,?,?)');
        foreach($coordinates as $id=>[$lat,$lon]) {$insert->execute([$id,$lat,$lon]);}
        $shapes=[new BoundingBox(-1,-1,1,1),new BoundingBox(-1,170,1,-170),new BoundingBox(0,-180,0,-180),
            new Circle(new GeoPoint(0,0),50,'km'),new Circle(new GeoPoint(0,179.9),50,'km'),new Circle(new GeoPoint(89.9,0),30,'km'),
            new Circle(new GeoPoint(0,0),0,'km'),
            new Circle(new GeoPoint(0,0),(new GeoPoint(0,0))->distanceTo(new GeoPoint(1,1),'km'),'km')];
        foreach([false,true] as $named) {
            foreach([false,true] as $atan2) {
                $q=new NearbyQuery($dialect,named:$named,atan2:$atan2);
                foreach($shapes as $shape) {
                    $f=$q->within($shape);$statement=$db->prepare('SELECT id FROM places WHERE '.$f->sql().' ORDER BY id');
                    $statement->execute($f->bindings());$actual=array_map('intval',$statement->fetchAll(PDO::FETCH_COLUMN));
                    $expected=[];foreach($coordinates as $id=>[$lat,$lon]) {if($shape->contains(new GeoPoint($lat,$lon)))$expected[]=$id;}
                    self::assertSame($expected,$actual,get_class($shape).': '.$f->sql());
                }
                $distance=$q->distanceExpression(new GeoPoint(0,0),'km');
                $where=$q->within(new Circle(new GeoPoint(0,0),100,'km'));
                if($named) self::assertSame([],array_intersect_key($distance->bindings(),$where->bindings()));
                $stmt=$db->prepare('SELECT id, '.$distance->sql().' AS distance_km FROM places WHERE '.$where->sql().' ORDER BY distance_km');
                $stmt->execute(array_merge($distance->bindings(),$where->bindings()));
                $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);self::assertCount(3,$rows);
                foreach($rows as $row) {
                    [$lat,$lon]=$coordinates[(int)$row['id']];
                    self::assertEqualsWithDelta((new GeoPoint(0,0))->distanceTo(new GeoPoint($lat,$lon),'km'),(float)$row['distance_km'],1e-9);
                }
            }
        }
        $polygon=Polygon::fromArray([[0,0],[0,2],[2,0]]);
        $filter=(new NearbyQuery($dialect))->within($polygon);
        self::assertTrue($filter->requiresPostFiltering());
        $stmt=$db->prepare('SELECT id FROM places WHERE '.$filter->sql());$stmt->execute($filter->bindings());
        self::assertContains(3,array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN))); // candidate at (1,1)
        $db->exec('DROP TABLE places');
    }
}
