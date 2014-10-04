<?php namespace Theorem\FatCrud\Commands;

use Theorem\FatCrud\Cache;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Theorem\FatCrud\Generators\ViewGenerator;
use Illuminate\Support\Pluralizer;
use DB;
use File;

class CrudCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'crud:create';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Automatically generate a set of views for a CRUD model';

    /**
     * Model generator instance.
     *
     * @var Way\Generators\Generators\ResourceGenerator
     */
    protected $generator;

    /**
     * File cache.
     *
     * @var Cache
     */
    protected $cache;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(ViewGenerator $generator, Cache $cache)
    {
        parent::__construct();

        $this->generator = $generator;
        $this->cache = $cache;
    }

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function fire()
	{
        // Scaffolding should always begin with the singular
        // form of the now.
        $this->model = Pluralizer::singular($this->argument('name'));

        // $this->fields = $this->option('fields');
        //
        // if (is_null($this->fields))
        // {
        //     throw new MissingFieldsException('You must specify the fields option.');
        // }
        
        
        // $columns = $schema->listTableColumns($this->argument('name'));
        
        // $model = \App::make($this->model);
        // $model = $model->getAttributes();

		// Get the table attributes
        $this->fields = $this->getAllColumnsNames($this->argument('name'));

        // We're going to need access to these values
        // within future commands. I'll save them
        // to temporary files to allow for that.
        $this->cache->fields($this->fields);
        $this->cache->modelName($this->model);

        if (is_null($this->fields))
        {
            throw new MissingFieldsException('You must specify the fields option.');
        }
        
        $this->generateViews();
	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return array(
            // array('model', InputArgument::REQUIRED, 'Name of Eloquent Model.'),
            array('name', InputArgument::REQUIRED, 'Name of Eloquent Model.'),
		);
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array(
			array('only', null, InputOption::VALUE_OPTIONAL, 'CRUD operations to create.', null),
			array('except', null, InputOption::VALUE_OPTIONAL, 'CRUD operations to exclude.', null),
            array('path', null, InputOption::VALUE_OPTIONAL, 'Path to views directory.', app_path() . '/views'),
            array('template', null, InputOption::VALUE_OPTIONAL, 'Path to template.', __DIR__.'/../Generators/templates/view.txt'),
		);
	}

    /**
     * Call generate:views
     *
     * @return void
     */
    protected function generateViews()
    {
        $viewsDir = app_path() . '/views';
        
        // if (!is_null($this->option('path'))) {
        //     $viewsDir = app_path() . $this->option('path');
        // }
        
        $container = $viewsDir . '/' . Pluralizer::plural($this->model);
        $layouts = $viewsDir . '/layouts';
        
        $only = $this->option('only');
        $except = $this->option('except');
        
        if (!is_null($only)) {
            
            $views = explode('|', $only);
            
        } elseif (!is_null($except)) {
            
            $views = explode('|', $except);
            
        } else {
            
            $views = array('index', 'show', 'create', 'edit');
            
        }

        // dd($container);
        $this->generator->folders(
            array($container)
        );
        
        $views[] = 'scaffold';
        $this->generator->folders([$layouts]);

        // Let's filter through all of our needed views
        // and create each one.
        foreach($views as $view)
        {
            $path = $view === 'scaffold' ? $layouts : $container;
            $path = $this->getPath($path, $view);
            $this->generateView($view, $path);
        }
    }

    /**
     * Generate a view
     *
     * @param  string $view
     * @param  string $path
     * @return void
     */
    protected function generateView($view, $path)
    {
        if (File::exists($path)) {
            if (!$this->confirm("The file $path already exists. Do you want me to overwrite it? [yes|no]")) {
                return false;
            }
        }   
        
        // $template = $this->option('template');
        $template = $this->getViewTemplatePath($view);

        $this->printResult($this->generator->make($path, $template), $path);
        
    }

    /**
     * Get the path to the template for a view.
     *
     * @return string
     */
    protected function getViewTemplatePath($view = 'view')
    {
        return __DIR__."/../Generators/Templates/Views/{$view}.txt";
    }

    /**
     * Provide user feedback, based on success or not.
     *
     * @param  boolean $successful
     * @param  string $path
     * @return void
     */
    protected function printResult($successful, $path)
    {
        if ($successful)
        {
            return $this->info("Created {$path}");
        }

        $this->error("Could not create {$path}");
    }

    /**
     * Get the path to the file that should be generated.
     *
     * @return string
     */
    protected function getPath($path, $view)
    {
       return  $path . '/' . strtolower($view) . '.blade.php';
    }
    
    public function getAllColumnsNames($table)
        {
            switch (DB::connection()->getConfig('driver')) {
                case 'pgsql':
                    $query = "SELECT column_name FROM information_schema.columns WHERE table_name = '".$table."'";
                    $column_name = [
                        'name' => 'column_name', 
                        'type' => 'data_type'
                    ];
                    $reverse = true;
                    break;

                case 'mysql':
                    $query = 'SHOW COLUMNS FROM ' . $table;
                    $column_name = [
                        'name' => 'Field', 
                        'type' => 'Type'
                    ];
                    $reverse = false;
                    break;

                case 'sqlsrv':
                    $parts = explode('.', $table);
                    $num = (count($parts) - 1);
                    $table = $parts[$num];
                    $query = "SELECT column_name FROM ".DB::connection()->getConfig('database').".INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = N'".$table."'";
                    $column_name = [
                        'name' => 'column_name', 
                        'type' => 'data_type'
                    ];
                    $reverse = false;
                    break;

                default: 
                    $error = 'Database driver not supported: '.DB::connection()->getConfig('driver');
                    throw new Exception($error);
                    break;
            }

            $columns = array();

            foreach(DB::select($query) as $column) {

                $excludes = [
                    'id',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                ];

                if (in_array(strtolower($column->{$column_name['name']}), $excludes)) {
                    continue(1);
                }

                $columns[] = [
                    'name' => $column->{$column_name['name']},
                    'type' => $column->{$column_name['type']}
                ];
            }

            if ($reverse) {
                $columns = array_reverse($columns);
            }

            return $columns;
        }

}
