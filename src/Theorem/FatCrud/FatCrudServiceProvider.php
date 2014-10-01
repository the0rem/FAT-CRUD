<?php namespace Theorem\FatCrud;

use Theorem\FatCrud\Generators\Commands;
use Theorem\FatCrud\Generators\Generators;
use Theorem\FatCrud\Generators\ViewGenerator;
use Theorem\FatCrud\Cache;

use Illuminate\Support\ServiceProvider;
use Theorem\FatCrud\Commands\CrudCommand;

class FatCrudServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('theorem/fat-crud');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
        // $this->registerScaffoldGenerator();
        // $this->registerViewGenerator();
        // $this->registerFormDumper();
        
		$this->app->bind('fatcrud.crudcommand', function($app) {
			$cache = new Cache($app['files']);
			$generator = new ViewGenerator($app['files'], $cache);
            
			return new CrudCommand($generator, $cache);
		});

		$this->commands('fatcrud.crudcommand');
	}

	/**
	 * Register generate:scaffold
	 *
	 * @return Commands\ScaffoldGeneratorCommand
	 */
	protected function registerScaffoldGenerator()
	{
		$this->app['generate.scaffold'] = $this->app->share(function($app)
		{
			$generator = new Generators\ResourceGenerator($app['files']);
			$cache = new Cache($app['files']);

			return new Commands\ScaffoldGeneratorCommand($generator, $cache);
		});
	}

	/**
	 * Register generate:view
	 *
	 * @return Commands\ViewGeneratorCommand
	 */
	protected function registerViewGenerator()
	{
		$this->app['generate.view'] = $this->app->share(function($app)
		{
			$cache = new Cache($app['files']);
			$generator = new Generators\ViewGenerator($app['files'], $cache);

			return new Commands\ViewGeneratorCommand($generator);
		});
	}

	/**
	 * Register generate:scaffold
	 *
	 * @return Commands\ScaffoldGeneratorCommand
	 */
	protected function registerResourceGenerator()
	{
		$this->app['generate.resource'] = $this->app->share(function($app)
		{
			$cache = new Cache($app['files']);
			$generator = new Generators\ResourceGenerator($app['files'], $cache);

			return new Commands\ResourceGeneratorCommand($generator, $cache);
		});
	}

	/**
	 * Register generate:migration
	 *
	 * @return Commands\MigrationGeneratorCommand
	 */
	protected function registerFormDumper()
	{
		$this->app['generate.form'] = $this->app->share(function($app)
		{
			$gen = new Generators\FormDumperGenerator($app['files'], new \Mustache_Engine);

			return new Commands\FormDumperCommand($gen);
		});
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return [];
	}

}
