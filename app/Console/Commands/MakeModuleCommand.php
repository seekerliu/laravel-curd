<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;
use Symfony\Component\Console\Exception\RuntimeException;

class MakeModuleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:module {module} {comment} {--m|migration} {--model=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make a module, include controller, model(migration), repository, criteria, view, route, policy.';

    protected $files;
    protected $composer;

    protected $models = ['controller', 'model', 'repository', 'criteria', 'view', 'route', 'policy'];
    protected $module;  //模块英文名称, 请使用首字母大写的形式
    protected $comment; //模块中文名称

    protected $model = null;
    protected $config = null;

    /**
     * Create a new command instance.
     *
     * @param Filesystem $filesystem
     * @param Composer $composer
     */
    public function __construct(Filesystem $filesystem, Composer $composer)
    {
        parent::__construct();

        $this->files = $filesystem;
        $this->composer = $composer;
    }

    /**
     * Execute the console command.
     *
     */
    public function handle()
    {
        $this->module = $this->argument('module');

        $models = $this->option('model') ? explode(',', $this->option('model')) : $this->models;
        $this->comment = $this->argument('comment');

        //生成全部或指定类型的文件
        foreach($models as $model) {
            $model = strtolower($model);

            if(in_array($model, $this->models)) {
                if($model=='model') {
                    $this->makeModel($this->option('migration'));
                } else {
                    $this->write($model);
                }
            }
        }



        //重新生成 autoload.php 文件
        $this->composer->dumpAutoloads();
    }

    /**
     * 存储的目标路径
     * @return string
     */
    private function getDirectory()
    {
        switch($this->model) {
            case 'criteria':
                return $this->config['directory_path'].DIRECTORY_SEPARATOR.Str::plural($this->module);
            case 'view':
                return $this->config['directory_path'].DIRECTORY_SEPARATOR.lcfirst(Str::plural($this->module));

            //此处添加新类别

            default:
                return $this->config['directory_path'];
        }
    }

    /**
     * 获取模板变量替换表
     * @return array
     */
    private function getVars()
    {
        switch($this->model) {
            case 'controller':
                return [
                    'controller_namespace' => $this->config['namespace'],
                    'module_controller' => Str::plural($this->module).'Controller',
                    'module_name' => $this->module,
                    'module_var_name' => lcfirst($this->module),
                    'module_var_plural_name' => lcfirst(Str::plural($this->module)),
                    'repository_namespace' => $this->config['repository_namespace'],
                    'module_repository' => Str::plural($this->module).'Repository',
                ];
            case 'repository':
                return [
                    'module_name' => $this->module,
                    'repository_namespace' => $this->config['namespace'],
                    'module_repository' => Str::plural($this->module).'Repository',
                ];
            case 'criteria':
                return [
                    'module_criteria_namespace' => $this->config['namespace'].'\\'.Str::plural($this->module),
                    'module_criteria_name' => 'SearchIn'.$this->module,
                ];
            case 'view':
                return [
                    'module_name' => lcfirst($this->module),
                    'module_model_name' => $this->module,
                    'module_plural_name' => lcfirst(Str::plural($this->module)),
                    'module_snake_name' => Str::snake($this->module),
                    'module_comment' => $this->comment,
                ];
            case 'route':
                return [
                    'module_name' => $this->module,
                    'module_lower_name' => lcfirst($this->module),
                    'module_plural_lower_name' => lcfirst(Str::plural($this->module)),
                    'module_plural_name' => Str::plural($this->module),
                    'module_comment' => $this->comment,
                ];
            case 'policy':
                return [
                    'module_name' => $this->module,
                    'module_var_name' => lcfirst($this->module),
                    'module_model_namespace' => $this->config['model_namespace'].'\\'.$this->module,
                ];
            //此处添加新类别

            default:
                return [];
        }
    }

    /**
     * 获取生成的模板文件名称
     * @param $stubName
     * @return string
     */
    private function getTemplateName($stubName)
    {
        switch($this->model) {
            case 'controller':
                return Str::plural($this->module).'Controller.php';
            case 'repository':
                return Str::plural($this->module).'Repository.php';
            case 'criteria':
                return Str::plural($this->module).DIRECTORY_SEPARATOR.'SearchIn'.$this->module.'.php';
            case 'view':
                return lcfirst(Str::plural($this->module)).DIRECTORY_SEPARATOR.$stubName.'.blade.php';
            case 'route':
                return lcfirst($this->module).'.php';
//            case 'policy':
//                return $this->module.'Policy.php';
            //此处添加新类别

            default:
                return $this->module.ucfirst($this->model).'.php';
        }
    }

    private function write($model)
    {
        $this->setModel($model);
        $this->setConfig();

        if($this->create()) {
            $this->info('Success to make a '.$this->module.' '.ucfirst($this->model));
        } else {
            $this->info('Fail to make a '.$this->module.' '.ucfirst($this->model));
        }

        $this->model = null;
        $this->config = null;
    }

    /**
     * 生成 Model, Migration
     * @param bool $migration
     */
    private function makeModel($migration=false)
    {
        $arguments = [
            'name' => $this->module,
            '-v' => true,
            '--migration' => $migration,
        ];

        //生成 Model, Migration 文件
        try{
            \Artisan::call('make:model', $arguments);
            $this->info('Success to make a '.$this->module.' Model.');
            if(!!$migration) {
                $this->info('Success to make a '.$this->module.' Migration.');
            }
        } catch (RuntimeException $e) {
            $this->info('Fail to make a '.$this->module.' Model.');
            if($migration) {
                $this->info('Fail to make a '.$this->module.' Migration.');
            }
        }
    }

    /**
     * @return boolean
     */
    private function create()
    {
        //创建模板保存目录
        if(!$this->createDirectory($this->getDirectory())) {
            return false;
        }

        //获取模板文件
        $stubFiles = $this->getStubFiles();

        //获取模板变量
        $vars = $this->getVars();

        //生成模板
        $result = $this->createTemplate($stubFiles, $vars);

        //输出并返回结果
        return $this->createResult($result);
    }

    /**
     * 创建文件
     * @param $stubFiles
     * @param $vars
     * @return mixed $template
     */
    private function createTemplate($stubFiles, $vars)
    {
        $result = [];

        foreach($stubFiles as $stubName => $stubFile) {
            $classFile = $this->getRenderedStub($stubFile, $vars);
            //输出内容至模板文件
            $templatePath = $this->config['directory_path'].DIRECTORY_SEPARATOR.$this->getTemplateName($stubName);

            //判断文件是否存在
            $template = 0;
            if(!$this->files->isFile($templatePath)) {
                $template = $this->files->put($templatePath, $classFile);
            }

            //此模板的生成结果
            $result[$stubName] = [
                'path' => $templatePath,
                'result' => $template,
            ];
        }

        return $result;
    }

    private function createResult($result)
    {
        $final = true;

        foreach($result as $key=>$item) {
            if($item['result']) {
                $this->info('Success to create '.$key.'.stub to '.$item['path']);
            } else {
                $this->info('Fail to create '.$key.'.stub to '.$item['path']);
                $final = false;
            }
        }

        return $final;
    }

    /**
     * 获取 stub 源文件内容
     * @return array
     */
    private function getStubFiles()
    {
        $stubFiles = [];

        foreach($this->config['stubs'] as $stub) {
            $stubFiles[$stub] = $this->files->get(resource_path('stubs/'.ucfirst($this->model)).DIRECTORY_SEPARATOR.$stub.'.stub');
        }

        return $stubFiles;
    }

    /**
     * 替换 stub 中的变量， 获取渲染后的模板文件
     * @param $stub
     * @param $templateVars
     * @return array
     */
    private function getRenderedStub($stub, $templateVars)
    {
        //替换变量
        foreach ($templateVars as $search => $replace) {
            $stub = str_replace('$'.$search, $replace, $stub);
        }

        return $stub;
    }

    /**
     * 检查路径是否存在,不存在创建一个,并赋予775权限
     * @param $directory
     * @return bool
     */
    private function createDirectory($directory)
    {
        if(!$directory)
            return false;

        if(!$this->files->isDirectory($directory)){
            return $this->files->makeDirectory($directory, 0755, true);
        }

        return true;
    }

    /**
     * @param mixed $model
     */
    private function setModel($model)
    {
        $this->model = $model;
    }

    private function setConfig()
    {
        $this->config = \Config::get('module.' .$this->model);
    }
}
