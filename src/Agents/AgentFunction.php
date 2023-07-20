<?php

namespace Adrenallen\AiAgentsLaravel\Agents;

/**
 * AgentFunction
 * A class to represent a function that an agent can perform
 */
class AgentFunction {

    public $name;
    public $description;
    public $parameters;
    public $requiredParameters;

    // TODO: Pull in the param types and descriptions from the docblock
    /*
    * @param \ReflectionMethod $method
    * @return AgentFunction
    */
    public static function createFromMethodReflection(\ReflectionMethod $method): AgentFunction {
        $name = $method->name;

        $methodDetails = self::phpDocParamDetails($method);

        $description = $methodDetails['@aiagent-description'][0];


        $parameters = [];
        $requiredParameters = [];

        if (key_exists('@param', $methodDetails)) {
            foreach($methodDetails['@param'] as $param) {
                $parameters[] = self::splitParamComment($param);
            }

            foreach ($method->getParameters() as $param) {
                if (!$param->isOptional()) {
                    $requiredParameters[] = $param->getName();
                }
            }
        }
        

        return new AgentFunction($name, $description, $parameters, $requiredParameters);
    }

    /**
     * Checks if a method is setup for an agent function
     *
     * @param \ReflectionMethod $method
     * @return boolean
     */
    public static function isMethodForAgent(\ReflectionMethod $method): bool {
        return strpos($method->getDocComment(), "@aiagent-description") !== false;
    }

    public static function phpDocParamDetails(\ReflectionMethod $method) : array {
        // Retrieve the full PhpDoc comment block
        $doc = $method->getDocComment();

        // Trim each line from space and star chars 
        $lines = array_map(function($line){
            return trim($line, " *");
        }, explode("\n", $doc));

        // Retain lines that start with an @
        $lines = array_filter($lines, function($line){
            return strpos($line, "@") === 0;
        });
        
        $args = [];

        // Push each value in the corresponding @param array
        foreach($lines as $line){
            list($param, $value) = explode(' ', $line, 2);
            $args[$param][] = $value;
        }

        return $args;
    }

    
    // splits a param comment from a doc block into
    // a hash that can be read by a chat model
    private static function splitParamComment($param) : array {
        $paramObj = [
            'name' => '',
            'type' => '',
            'description' => '',
        ];

        $paramSplit = explode(" ", $param, 3);

        $paramObj['type'] = self::convertPHPTypeToOpenAIType($paramSplit[0]);
        $paramObj['name'] = str_replace('$', '', $paramSplit[1]);   //get rid of the $ in the name of variable
        if (count($paramSplit) > 2){
            $paramObj['description'] = $paramSplit[2];
        }


        return $paramObj;
    }

    private static function convertPHPTypeToOpenAIType($type) {
        switch($type) {
            case "string":
                return "string";
            case "int":
                return "integer";
            case "float":
                return "number";
            case "bool":
                return "boolean";
            case "array":
                return "array";
            case "object":
                return "object";
            default:
                return "string";
        }
    }



    public function __construct(string $name, string $description, array $parameters, array $requiredParameters = []) {
        $this->name = $name;
        $this->description = $description;
        $this->parameters = $parameters;
        $this->requiredParameters = $requiredParameters;
    }

    public function getParameters(): array {
        return $this->parameters;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getDescription(): string {
        return $this->description;
    }



    

}