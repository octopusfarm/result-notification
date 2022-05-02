## ResultNotification
An extension of Martin Fowler's [Replacing Throwing Exceptions with Notification in Validations](https://martinfowler.com/articles/replaceThrowWithNotification.html).

It's very common for code to account for the **normal course**, while rarely addressing the **exception course**. (These are formal terms, and "exception course" should not be taken to mean "throwing exceptions." It's the other way around: exceptions are *one possible solution* to handling the exception course.) **When you use `ResultNotification`, you will find that it becomes second nature to account for both.**

Internally, `ResultNotification` manages two arrays (with nested subscripts and values addressable via dot notation) so that you can separately track results (things that worked as desired) and errors (things that failed.) The idea is that since you should expect certain operations to fail on a regular basis (due to network issues, configuration problems, or incorrect input), **you should always write your code to assume such events.**

If you use this library, you may find that your code becomes significantly smaller vs. rigging up your own boilerplate for tracking these things separately. The first project I used this pattern with shrank by about 15%!

### Basics
`ResultNotification` has two internal arrays, one of which holds "results" (things that worked as desired) and "errors" (things that didn't.)

It allows you to set values using dot notation, e.g.:
```
use OctopusFarm\ResultNotification\ResultNotification as Result;
$response = new Result();

// Equivalent to ['user' => ['login' => ['status']]] = $status;
$response->addResult('user.login.status', $status);

// Retrieve
$status = $response->result('user.login.status');

// Check validity (true in this case, as no errors have been added)
$valid = $response->valid();
```

When you serialize a `ResultNotification`, it looks like this:
```
[
    'valid' => true|false,
    'results' => [
        'things that worked, if any'
    ],
    'errors' => [
        'things that failed, if any'
    ]
]
```
You can directly JSON-encode a `ResultNotification` simply by passing it to `json_encode()`. It can be turned into an array by calling `serialize()`. To turn an array (from e.g. a response) into a live object, you can call `ResultNotification::fromArray($array)`. This makes it very easy to pass such messages between services, and between back-end servers and front-end JavaScript.

### Examples (or just run `demo.php`)
```
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
```

### Theory
This is a somewhat different paradigm for error handling. I allege that it's *better* than throwing exceptions for every last thing, or waiting for the runtime or some library to do the same.

Some aspects of this paradigm:
* This is good for in-program state passing, but it's also good for communication *between* processes. It can easily clean up arbitrary one-off response schemas. You've probably seen services return data with different conventions: `success`, `valid`, `data`, `error`, `errors`, various domain-specific subscript names; schema that can only return one result or one error, and which create headaches when it becomes necessary to return more; situations where you have to check whether the transaction succeeded or not by looking for the presence of some subscript or flag that's not the same between endpoints, even though they run on the same application... you get the idea. `ResultNotification` can standardize all that because there are always three subscripts: `valid`, `results`, and `errors`. As soon as you've got a live `ResultNotification`, all you have to do is call its `valid()` method to see whether you're good to go.
* Always assume that things that can fail, will fail. It's usually not faster to say to yourself, "I'll just come back and account for that later." `ResultNotification` **removes a great deal of friction** from doing so, as you now have a unified mechanism for tracking the ongoing state of whatever your process has been doing.
* When you can reasonably determine whether a method will throw without calling it, do so; and if it will throw, *don't call it.* Instead, `$result->addError('...');` and then branch somewhere else, e.g. by returning early.
* It's also useful to check `$result->valid()` before proceeding to carry out operations that wouldn't make sense. For example, if you weren't able to successfully build a request, it wouldn't make sense to prepare to transmit it.
* `ResultNotification` is *very, very good* at propagating single or multiple errors out from deep within complex code heirarchies. (Compare this to getting an exception, fixing something, then getting the *next* exception, and so on.) If you're writing a feature-rich library that can do many things, and which has many well-separated components, each can have its own `ResultNotification`, *and they can share and merge them as well.* When you use this library, nothing stands between you and determining *exactly* what went wrong, and where.

### History
Years ago, I found that whenever I wrote code that *should be expected to fail from time to time* — you know, talking to a remote host or a database server, or handling some request that couldn't be processed for whatever reason — I kept having to create arrays (ordered maps, if you're not familiar with PHP) to store results and errors, pass them around, and merge them. This was tedious, and lead to lots of copypasta.

I knew there had to be a better way to do this, so I started looking for a solution. It didn't take long to find Martin Fowler's writeup on notifications. This is not an attempt to implement his advice word-for-word, but it did serve as the starting point and caused me to ask myself all the right questions.


