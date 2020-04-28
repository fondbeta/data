<?php

namespace atk4\data\tests;

use atk4\core\AtkPhpunit;
use atk4\data\Model;
use atk4\data\Persistence;
use atk4\data\tests\Model\Person as Person;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class CSVTest extends AtkPhpunit\TestCase
{
    protected $file;
    protected $file2;

    protected function setUp(): void
    {
        parent::setUp();

        // better to skip this test on Windows, prevent permissions issues
        // see also https://github.com/atk4/data/issues/271
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->markTestSkipped('Skip on Windows');
        }

        $this->file = sys_get_temp_dir() . '/atk4_test__data__a.csv';
        $this->file2 = sys_get_temp_dir() . '/atk4_test__data__b.csv';
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (file_exists($this->file)) {
            unlink($this->file);
        }
        if (file_exists($this->file2)) {
            unlink($this->file2);
        }
    }

    protected function setDB($data): void
    {
        $f = fopen($this->file, 'w');
        fputcsv($f, array_keys(reset($data)));
        foreach ($data as $row) {
            fputcsv($f, $row);
        }
        fclose($f);
    }

    protected function getDB(): array
    {
        $f = fopen($this->file, 'r');
        $keys = fgetcsv($f);
        $data = [];
        while ($row = fgetcsv($f)) {
            $data[] = array_combine($keys, $row);
        }
        fclose($f);

        return $data;
    }

    /**
     * Test constructor.
     */
    public function testTestcase()
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];

        $this->setDB($data);
        $data2 = $this->getDB();
        $this->assertEquals($data, $data2);
    }

    public function testLoadAny()
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];

        $this->setDB($data);

        $p = new Persistence\CSV($this->file);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');
        $m->loadAny();

        $this->assertEquals('John', $m['name']);
        $this->assertEquals('Smith', $m['surname']);
    }

    public function testLoadAnyException()
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];

        $this->setDB($data);

        $p = new Persistence\CSV($this->file);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');
        $m->loadAny();
        $m->loadAny();

        $this->assertEquals('Sarah', $m['name']);
        $this->assertEquals('Jones', $m['surname']);

        $m->tryLoadAny();
        $this->assertFalse($m->loaded());
    }

    public function testPersistenceCopy()
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith', 'gender' => 'M'],
            ['name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
        ];

        $this->setDB($data);

        $p = new Persistence\CSV($this->file);
        $p2 = new Persistence\CSV($this->file2);

        $m = new Person($p);

        $m2 = $m->withPersistence($p2);

        foreach ($m as $row) {
            $m2->save($m);
        }

        $this->assertEquals(
            file_get_contents($this->file2),
            file_get_contents($this->file)
        );
    }

    /**
     * Test export.
     */
    public function testExport()
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];
        $this->setDB($data);

        $p = new Persistence\CSV($this->file);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        $this->assertEquals([
            ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ['id' => 2, 'name' => 'Sarah', 'surname' => 'Jones'],
        ], $m->export());

        $this->assertEquals([
            ['surname' => 'Smith'],
            ['surname' => 'Jones'],
        ], $m->export(['surname']));
    }
}
