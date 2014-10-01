<?php namespace Theorem\FatCrud\Generators;

use Illuminate\Support\Pluralizer;
use Theorem\FatCrud\Generators\Generator;

class ViewGenerator extends Generator {

    /**
     * Fetch the compiled template for a view
     *
     * @param  string $template Path to template
     * @param  string $name
     * @return string Compiled template
     */
    protected function getTemplate($template, $name)
    {
        $this->template = $this->file->get($template);

        // if ($this->needsScaffolding($template))
        // {
            return $this->getScaffoldedTemplate($name);
        // }

        // Otherwise, just set the file
        // contents to the file name
        return $name;
    }

    /**
     * Get the scaffolded template for a view
     *
     * @param  string $name
     * @return string Compiled template
     */
    protected function getScaffoldedTemplate($name)
    {
        $model = $this->cache->getModelName();  // post
        $models = Pluralizer::plural($model);   // posts
        $Models = ucwords($models);             // Posts
        $Model = Pluralizer::singular($Models); // Post

        // Create and Edit views require form elements
        if ($name === 'create.blade' or $name === 'edit.blade')
        {
            $formElements = $this->makeFormElements();

            $this->template = str_replace('{{formElements}}', $formElements, $this->template);
        }

        // Replace template vars in view
        foreach(array('model', 'models', 'Models', 'Model') as $var)
        {
            $this->template = str_replace('{{'.$var.'}}', $$var, $this->template);
        }

        // And finally create the table rows
        list($headings, $fields, $editAndDeleteLinks) = $this->makeTableRows($model);
        
        $this->template = str_replace('{{headings}}', implode(PHP_EOL."\t\t\t\t", $headings), $this->template);
        $this->template = str_replace('{{fields}}', implode(PHP_EOL."\t\t\t\t\t", $fields) . PHP_EOL . $editAndDeleteLinks, $this->template);

        return $this->template;
    }

    /**
     * Create the table rows
     *
     * @param  string $model
     * @return Array
     */
    protected function makeTableRows($model)
    {
        $models = Pluralizer::plural($model); // posts

        $fields = $this->cache->getFields();
        
        // First, we build the table headings
        $headings = array_map(function($field) {
            return '<th>' . studly_case($field['name']) . '</th>';
        }, $fields);

        // And then the rows, themselves
        $fields = array_map(function($field) use ($model) {
            return "<td>{{{ \$$model->" . $field['name'] . " }}}</td>";
        }, $fields);

        // Now, we'll add the edit and delete buttons.
        $editAndDelete = <<<EOT
                    <td>
                        {{ Form::open(array('style' => 'display: inline-block;', 'method' => 'DELETE', 'route' => array('{$models}.destroy', \${$model}->id))) }}
                            {{ Form::submit('Delete', array('class' => 'btn btn-danger')) }}
                        {{ Form::close() }}
                        {{ link_to_route('{$models}.edit', 'Edit', array(\${$model}->id), array('class' => 'btn btn-info')) }}
                    </td>
EOT;

        return array($headings, $fields, $editAndDelete);
    }

    /**
     * Add Laravel methods, as string,
     * for the fields
     *
     * @return string
     */
    public function makeFormElements()
    {
        $formMethods = array();

        foreach($this->cache->getFields() as $id => $value)
        {
            $formalName = ucwords(str_replace('_', ' ', $value['name']));
            
            // TODO: Add handling for different database types
            // Field type returned can contain extra properties such as unsigned, length etc...
            // Split the result to get the type, length, and if unsigned
            
            $type = $value['type'];
            $unsigned = false;
            $limit = false;
            
            if (strstr($value['type'], ' ') !== false) {
                list($type, $unsigned) = explode(' ', $value['type']);
            }
            
            // Check type for a length limit
            if (strstr($type, '(') !== false) {
                
                preg_match('/\((.*)\)$/', $type, $limit);
                
                if (!empty($limit[1])) {
                    $limit = $limit[1];
                } else {
                    $limit = '';
                }
                
                // Remove limit from the type
                $type = preg_replace('/\(.*\)$/', '', $type);
                
            }
            
            
            print $type . ' - ' . $unsigned . ' - ' . json_encode($limit) . PHP_EOL;
            
            // TODO: add remaining types
            switch($type)
            {
                case 'int':
                
                    // Create a range for the input to be validated against
                    if ($unsigned !== false) {
                        
                        $min = 0;
                        $max = pow(2, $limit) - 1;
                        
                    } else {
                        
                        $halfLimit = pow(2, $limit) / 2;
                        
                        $min = $halfLimit * -1;
                        $max = $halfLimit - 1;
                        
                    }
                
                    $element = "{{ Form::input('number', '" .$value['name'] . "', Input::old('" .$value['name'] . "'), ['class' => 'form-control', 'min' => " . $min . ", 'max' => " . $max . ", 'placeholder'=>'" . $formalName . "']) }}";
                    
                    break;
                    
                case 'varchar':
                
                    $element = "{{ Form::text('" .$value['name'] . "', Input::old('" .$value['name'] . "'), ['class' => 'form-control', 'maxlength' => '" . $limit . "', 'placeholder'=>'" . $formalName . "']) }}";
                    
                    break;

                case 'text':
                
                    $element = "{{ Form::textarea('" . $value['name'] . "', Input::old('" . $value['name'] . "'), array('class'=>'form-control', 'placeholder'=>'" . $formalName . "')) }}";
                    
                    break;

                case 'bool':
                
                    $element = "{{ Form::checkbox('" . $value['name'] . "') }}";
                    
                    break;

                case 'enum':

                    $options = explode(',', $limit);
                    array_map(function($option){
                        return str_replace('\'', '', $option);
                    }, $options);

                    $element = "{{ Form::select('" . $value['name'] . "', $options,
                    Input::old('" . $value['name'] . "'),
                    array('class'=>'form-control', 'placeholder'=>'" . $formalName . "')) }}";

                    break;

                default:
                
                    $element = "{{ Form::text('" . $value['name'] . "', Input::old('" . $value['name'] . "'), array('class'=>'form-control', 'placeholder'=>'" . $formalName . "')) }}";
                    
                    break;
            }

            // Now that we have the correct $element,
            // We can build up the HTML fragment
            $frag = <<<EOL
                <div class="form-group">
                    {{ Form::label('{$value['name']}', '{$formalName}', array("class" => "col-md-2 control-label")) }}
                    <div class="col-sm-10">
                      {$element}
                    </div>
                </div>
EOL;

            $formMethods[] = $frag;
        }

        return implode(PHP_EOL, $formMethods);
    }

    /**
     * Create any number of folders
     *
     * @param  string|array $folders
     * @return void
     */
    public function folders(array $folders)
    {
        foreach($folders as $folderPath)
        {
            if (! $this->file->exists($folderPath))
            {
                $this->file->makeDirectory($folderPath);
            }
        }
    }

}
