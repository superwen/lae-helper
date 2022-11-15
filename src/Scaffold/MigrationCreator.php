<?php

namespace Superwen\Admin\Helpers\Scaffold;

use Illuminate\Database\Migrations\MigrationCreator as BaseMigrationCreator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;

class MigrationCreator extends BaseMigrationCreator
{
    /**
     * @var string
     */
    protected $bluePrint = '';

    public $name;

    public function __construct(Filesystem $files, $customStubPath = null)
    {
        parent::__construct($files, $customStubPath);
    }

    /**
     * Create a new model.
     *
     * @param string    $name
     * @param string    $path
     * @param null      $table
     * @param bool|true $create
     *
     * @return string
     */
    public function create($name, $path, $table = null, $create = true)
    {
        $this->name = $name;

        $this->ensureMigrationDoesntAlreadyExist($name);

        $path = $this->getPath($name, $path);

        $stub = $this->files->get(__DIR__.'/stubs/create.stub');

        $this->files->put($path, $this->populateStub($stub, $table));

        $this->firePostCreateHooks($table, $path);


        return $path;
    }

    /**
     * Populate stub.
     *
     * @param string $name
     * @param string $stub
     * @param string $table
     *
     * @return mixed
     */
    protected function populateStub($stub, $table)
    {
        // Here we will replace the table place-holders with the table specified by
        // the developer, which is useful for quickly creating a tables creation
        // or update migration from the console instead of typing it manually.
        if (! is_null($table)) {
            $stub = str_replace(
                ['DummyClass', 'DummyTable', 'DummyStructure'],
                [$this->getClassName($this->name), $table, $this->bluePrint],
                $stub
            );
        }

        return $stub;
    }

    /**
     * Build the table blueprint.
     *
     * @param array      $fields
     * @param string     $keyName
     * @param bool|true  $useTimestamps
     * @param bool|false $softDeletes
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function buildBluePrint($fields = [], $keyName = 'id', $useTimestamps = true, $softDeletes = false)
    {
        $fields = array_filter($fields, function ($field) {
            return isset($field['name']) && !empty($field['name']);
        });

        if (empty($fields)) {
            throw new \Exception('Table fields can\'t be empty');
        }

        $rows[] = "\$table->increments('$keyName');\n";

        foreach ($fields as $field) {
            $column = "\$table->{$field['type']}('{$field['name']}')";

            if ($field['key']) {
                $column .= "->{$field['key']}()";
            }

            if (isset($field['default']) && $field['default']) {
                $column .= "->default('{$field['default']}')";
            }

            if (isset($field['comment']) && $field['comment']) {
                $column .= "->comment('{$field['comment']}')";
            }

            if (Arr::get($field, 'nullable') == 'on') {
                $column .= '->nullable()';
            }

            $rows[] = $column.";\n";
        }

        if ($useTimestamps) {
            $rows[] = "\$table->timestamps();\n";
        }

        if ($softDeletes) {
            $rows[] = "\$table->softDeletes();\n";
        }

        $this->bluePrint = trim(implode(str_repeat(' ', 12), $rows), "\n");

        return $this;
    }
}
