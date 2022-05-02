<?php

include('vendor/autoload.php');

use OctopusFarm\ResultNotification\ResultNotification as Result;


// Basics
$result = new Result();
$result->addResult("The first thing I tried", "Worked");
assert($result->valid());

$result->addError("The second thing I tried", "Failed");
assert(!$result->valid());


// Dot Notation
$cat = new Result();
$cat->setResult('pet.cat.sound', 'meow');
$cat->setResult('pet.cat.weight', 'low');

$dog = new Result();
$dog->setResult('pet.dog.sound', 'woof woof');
$dog->setResult('pet.dog.weight', 'moderate');

$thylacine = new Result();
$thylacine->setError('pet.thylacine.sound', 'unknown');
$thylacine->setResult('pet.thylacine.weight', 'moderate');


// Merging
$pets = new Result();
$pets->merge($cat);
$pets->merge($dog);
$pets->merge($thylacine);


// Serializing
$terseArray = $pets->serialize(true);
$verboseArray = $pets->serialize();


// JSON
echo json_encode($pets, JSON_PRETTY_PRINT) . "\n";

/*
Output of the above:
{
    "valid": false,
    "results": {
        "pet": {
            "cat": {
                "sound": "meow",
                "weight": "low"
            },
            "dog": {
                "sound": "woof woof",
                "weight": "moderate"
            },
            "thylacine": {
                "weight": "moderate"
            }
        }
    },
    "errors": {
        "pet": {
            "thylacine": {
                "sound": "unknown"
            }
        }
    }
}

*/