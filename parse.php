<?php
ini_set('display_errors', 'stderr');

// Parsing parameters
if($argc > 1)
{
    if($argc == 2 && $argv[1] == '--help')
    {
        echo(" The fiter-type script reads the source code in IPPcode23 from the standard input, 
    checks the lexical and syntactic correctness of the code 
    and writes the XML representation of the program according to the specification to the standard output
 Parser-specific error return codes:
        • 21 - wrong or missing header in the source code written in IPPcode23;
        • 22 - unknown or incorrect operation code in the source code written in IPPcode23;
        • 23 - other lexical or syntactic error of the source code written in IPPcode23.\n");
        exit(0);
    }
    else
        exit(10);
}

echo ("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");

// Looking for the header using regex
while($line = fgets(STDIN))
{
    if (preg_match("/^\s*(#.*)?$/", $line))
    {
        continue;
    }
    else if (preg_match("/^\s*\.ippcode23\s*(#.*)?$/i", $line)) 
    {
        echo ("<program language=\"IPPcode23\">\n");
        break; // OK
    }
    else
        exit(21);
}

# Nonterminals regex
$VAR = "/^(LF|TF|GF)@[a-zA-Z_\-$&%*!?][a-zA-Z_\-$&%*!?0-9]*$/";
$LABEL = "/^[a-zA-Z_\-$&%*!?][a-zA-Z_\-$&%*!?0-9]*$/";
$TYPE = "/^(int|string|bool)$/";

## Partial base regex
$INT_OCTAL = "[+-]?0[oO]?[0-7]+";
$INT_DECIMAL = "[+-]?([1-9][0-9]*|0)";
$INT_HEXA = "[+-]?0[xX][0-9a-fA-F]+";

## Partial const regex
$CONST_INT = "^int@($INT_OCTAL|$INT_DECIMAL|$INT_HEXA)$";
$CONST_BOOL = "^bool@(true|false)$";
$CONST_STRING = "^string@([^\\\]|\\\[0-9]{3})*$";
$CONST_NIL =  "^nil@nil$";

$CONST = "/($CONST_INT|$CONST_BOOL|$CONST_STRING|$CONST_NIL)/";

$counter = 1;

/**
 * Generates an argument XML element of a var type on STDOUT in accordance with the XML syntax.
 *
 * @param int $num Ordinal number of the argument.
 * @param string $text Variable identifier.
 * @return void
 */
function var_generator($num, $text){
    $newtext = htmlspecialchars($text, ENT_XML1);
    echo("\t\t<arg$num type=\"var\">$newtext</arg$num>\n");
}

/**
 * Generates an argument XML element of a label type on STDOUT in accordance with the XML syntax.
 *
 * @param int $num Ordinal number of the argument.
 * @param string $text Label identifier.
 * @return void
 */
function label_generator($num, $text){
    $newtext = htmlspecialchars($text, ENT_XML1);
    echo("\t\t<arg$num type=\"label\">$newtext</arg$num>\n");
}

/**
 * Generates an argument XML element of a constant type on STDOUT in accordance with the XML syntax.
 *
 * @param int $num Ordinal number of the argument.
 * @param string $text Constant notation.
 * @return void
 */
function const_generator($num, $text){
    $newtext = htmlspecialchars($text, ENT_XML1);
    $parts = explode("@", $newtext, 2);
    echo("\t\t<arg$num type=\"$parts[0]\">$parts[1]</arg$num>\n");
}

/**
 * Generates an argument XML element of a type type on STDOUT in accordance with the XML syntax.
 *
 * @param int $num Ordinal number of the argument.
 * @param string $type Type notation.
 * @return void
 */
function type_generator($num, $type){
    echo("\t\t<arg$num type=\"type\">$type</arg$num>\n");
}

/**
 * Processes a var nonterminal/operand into the corresponding XML element.
 *
 * @param string $token Variable identifier.
 * @param int $num Type Ordinal number of the nonterminal/operand.
 * @return void
 */
function var_process($token, $num){
    global $VAR;
    if (preg_match($VAR, $token))
    {
        var_generator($num, $token);
        return true;
    }
    else
        return false;
}

/**
 * Processes a label nonterminal/operand into the corresponding XML element.
 *
 * @param string $token Label identifier.
 * @param int $num Type Ordinal number of the nonterminal/operand.
 * @return void
 */
function label_process($token, $num){
    global $LABEL;
    if (preg_match($LABEL, $token))
    {
        label_generator($num, $token);
        return true;
    }
    else
        return false;
}

/**
 * Processes a symb nonterminal/operand into the corresponding XML element.
 *
 * @param string $token Symb notation.
 * @param int $num Type Ordinal number of the nonterminal/operand.
 * @return void
 */
function symb_process($token, $num){
    global $VAR, $CONST;
    if (preg_match($VAR, $token))
    {
        var_generator($num, $token);
        return true;
    }
    else if (preg_match($CONST, $token))
    {
        const_generator($num, $token);
        return true;
    }
    else
        return false;
}

/**
 * Processes a type nonterminal/operand into the corresponding XML element.
 *
 * @param string $token Type notation.
 * @param int $num Type Ordinal number of the nonterminal/operand.
 * @return void
 */
function type_process($token, $num){
    global $TYPE;
    if(preg_match($TYPE, $token))
    {
        type_generator($num, $token);
        return true;
    }
    else
        return false;
}

// Lines processing where operands processing
while($line = fgets(STDIN))
{
    $line = trim(explode("#", $line)[0]); // Getting rid of a comment line and spaces on the sides
    if($line == "") continue; // Skipping an empty line
    $splitted = preg_split("/\s+/", $line); // Splitting a string with spaces into words

    switch(strtoupper($splitted[0]))
    {
        case 'CREATEFRAME':
        case 'PUSHFRAME':
        case 'POPFRAME':
        case 'RETURN':
        case 'BREAK':
            if(count($splitted) != 1)
                exit(23);

            echo("\t<instruction order=\"$counter\" opcode=\"" . strtoupper($splitted[0]) . "\">\n");
            echo("\t</instruction>\n");
            $counter++;
            break;

        case 'DEFVAR': // <var>
        case 'POPS':
            if(count($splitted) != 2)
                exit(23);

            echo("\t<instruction order=\"$counter\" opcode=\"" . strtoupper($splitted[0]) . "\">\n");

            if (!var_process($splitted[1], 1))
                exit(23);

            echo("\t</instruction>\n");
            $counter++;
            break;
        
        case 'PUSHS': // <symb>
        case 'WRITE': 
        case 'EXIT':
        case 'DPRINT':
            if(count($splitted) != 2)
                exit(23);

            echo("\t<instruction order=\"$counter\" opcode=\"" . strtoupper($splitted[0]) . "\">\n");

            if (!symb_process($splitted[1], 1))
                exit(23);

            echo("\t</instruction>\n");
            $counter++;
            break;
        
        case 'CALL': // <label>
        case 'LABEL':
        case 'JUMP':
            if(count($splitted) != 2)
                exit(23);

            echo("\t<instruction order=\"$counter\" opcode=\"" . strtoupper($splitted[0]) . "\">\n");

            if (!label_process($splitted[1], 1))
                exit(23);

            echo("\t</instruction>\n");
            $counter++;
            break;

        case 'INT2CHAR': // <var> <symb>
        case 'STRLEN':
        case 'TYPE':
        case 'MOVE':
        case 'NOT':
            if(count($splitted) != 3)
                exit(23);

            echo("\t<instruction order=\"$counter\" opcode=\"" . strtoupper($splitted[0]) . "\">\n");

            if (!var_process($splitted[1], 1) || !symb_process($splitted[2], 2))
                exit(23);

            echo("\t</instruction>\n");
            $counter++;
            break;
        
        case 'READ': // <var> <type>
            if(count($splitted) != 3)
                exit(23);

            echo("\t<instruction order=\"$counter\" opcode=\"" . strtoupper($splitted[0]) . "\">\n");

            if (!var_process($splitted[1], 1) || !type_process($splitted[2], 2))
                exit(23);

            echo("\t</instruction>\n");
            $counter++;
            break;

        case 'ADD': // <var> <symb1> <symb2>
        case 'SUB':
        case 'MUL':
        case 'IDIV':
        case 'LT':
        case 'GT':
        case 'EQ':
        case 'AND':
        case 'OR':
        case 'STRI2INT':
        case 'CONCAT':
        case 'GETCHAR':
        case 'SETCHAR':
            if(count($splitted) != 4)
                exit(23);

            echo("\t<instruction order=\"$counter\" opcode=\"" . strtoupper($splitted[0]) . "\">\n");       

            if (!var_process($splitted[1], 1) || !symb_process($splitted[2], 2) || !symb_process($splitted[3], 3))
                exit(23);

            echo("\t</instruction>\n");
            $counter++;
            break;

        case 'JUMPIFEQ': // <label> <symb1> <symb2>
        case 'JUMPIFNEQ':
            if(count($splitted) != 4)
                exit(23);

            echo("\t<instruction order=\"$counter\" opcode=\"" . strtoupper($splitted[0]) . "\">\n");

            if (!label_process($splitted[1], 1) || !symb_process($splitted[2], 2) || !symb_process($splitted[3], 3))
                exit(23);

            echo("\t</instruction>\n");
            $counter++;
            break;
        
        default:
            exit(22);    
    }
}

echo ("</program>");
?>