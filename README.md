## ResultNotification
An extension of Martin Fowler's [Replacing Throwing Exceptions with Notification in Validations](https://martinfowler.com/articles/replaceThrowWithNotification.html).

It's very common for code to account for the **normal course**, while rarely addressing the **exception course**. (These are formal terms, and "exception course" should not be taken to mean "throwing exceptions." It's the other way around: exceptions are *one possible solution* to handling the exception course.) **When you use `ResultNotification`, you will find that it becomes second nature to account for both.**

Internally, `ResultNotification` manages two arrays (with nested subscripts and values addressable via dot notation) so that you can separately track results (things that worked as desired) and errors (things that failed.) The idea is that since you should expect certain operations to fail on a regular basis (due to network issues, configuration problems, or incorrect input), **you should always write your code to assume such events.**

If you use this library, you may find that your code becomes significantly smaller vs. rigging up your own boilerplate for tracking these things separately. The first project I used this pattern with shrank by about 15%!

### Practical Example
Instead of this...
```
// This can be any operation that might fail, whether it involves calling an API or not.
$customer = $this->customerVendor->loadCustomer($name)->responseToArray();

// This method is written with the assumption that the API call succeeded.
// At some point, it fails, and is then modified to handle $customer being empty.
// Hopefully, that "some point" happens before the code reaches production.
$this->doSomething($customer);
```

...do this:

```
$result = $this->customerVendor->loadCustomer($name);

// customerVendor has returned a ResultNotification, populated like this:
// Response code 200-399 => $result->setResult('customer', $decodedResponse);
// Response code 400-599 => $result->setError($responseCode, $errorFromRemoteServer);

// If any errors have been set, $result->valid() will return false.
if($result->valid()) {
    // Now, doSomething() only has to be written to process a valid response.
    $this->doSomething($result->result('customer'));
} else {
    // Here, we'd write an error to CloudWatch or whatever.
    $this->handleError($result->errors());
}

// Assume that whatever called this method wants to know whether it worked.
return $result->valid();
```
This is a lot more intentional. When you're looking at a `ResultNotification`, you know that you're dealing with something that may or may not have worked, and that you should check its `valid()` method before proceeding.

It's also perfectly acceptable to move the `if($result->valid()) { } else { }` block to another method, if the one you're calling `$this->customerVendor->loadCustomer()` in has to be responsible for other things.

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


// Set all at once
$result->setResults(['This will replace' => 'all Results']);
$result->setErrors(['This will replace' => 'all Errors']);


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
  

// Serializing: Array containing only the valid flag, and non-empty subscripts
$terseArray = $pets->serialize(true);


// Serializing: Array containing the valid flag and both subscripts, empty or not
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
* This is good for in-program state passing, but it's also good for communication *between* processes. It can easily clean up arbitrary one-off response schemas. You've probably seen services return data with different conventions: `success`, `valid`, `data`, `error`, `errors`, various domain-specific subscript names; schema that can only return one result or one error, and which create headaches when it becomes necessary to return more; situations where you have to check whether the transaction succeeded or not by looking for the presence of some subscript or flag that's not the same between endpoints, even though they run on the same application... you get the idea. `ResultNotification` can standardize all that because there are always three members: `bool $valid`, `array $results`, and `array $errors`. As soon as you've got a live `ResultNotification`, all you have to do is call its `valid()` method to see whether you're good to go.
* Always assume that things that can fail, will fail. It's usually not faster to say to yourself, "I'll just come back and account for that later." `ResultNotification` **removes a great deal of friction** from doing so, as you now have a unified mechanism for tracking the ongoing state of whatever your process has been doing.
* When you can reasonably determine whether a method will throw without calling it, do so; and if it will throw, *don't call it.* Instead, `$result->addError('...');` and then branch somewhere else, e.g. by returning early.
* It's also useful to check `$result->valid()` before proceeding to carry out operations that wouldn't make sense. For example, if you weren't able to successfully build a request, it wouldn't make sense to prepare to transmit it.
* `ResultNotification` is *very, very good* at propagating single or multiple errors out from deep within complex code heirarchies. (Compare this to getting an exception, fixing something, then getting the *next* exception, and so on.) If you're writing a feature-rich library that can do many things, and which has many well-separated components, each can have its own `ResultNotification`, *and they can share and merge them as well.* When you use this library, nothing stands between you and determining *exactly* what went wrong, and where.

### History
Years ago, I found that whenever I wrote code that *should be expected to fail from time to time* — you know, talking to a remote host or a database server, or handling some request that couldn't be processed for whatever reason — I kept having to create arrays (ordered maps, if you're not familiar with PHP) to store results and errors, pass them around, and merge them. This was tedious, and lead to lots of copypasta.

I knew there had to be a better way to do this, so I started looking for a solution. It didn't take long to find Martin Fowler's writeup on notifications. This is not an attempt to implement his advice word-for-word, but it did serve as the starting point and caused me to ask myself all the right questions.

### Q&A

#### Why "results" and "errors" rather than "successes" and "failures" or some similar terminology?
In as many words: **to avoid implying too much.**

"Result" is less specific than "success." The results could contain an "accepted for processing" message, which doesn't mean the same thing as "successful." (What if you call the API back later, and it has rejected the operation?) Likewise, an error is not necessarily a failure. It could be that a process _declined to try_ because it was temporarily out of resources, rather than because of any problem with your request. That's not the same as failure.

#### If I replace all my exceptions with notifications, where will I get the stack trace?
You want the stack trace because it will tell you the context of what went wrong, but `ResultNotification` _already gives you a way to propagate that information out to where you can use it, through as many layers as necessary._ This is like an `Exception`, except that it can carry multiple result and error messages (or objects, or whatever), and it doesn't spontaneously teleport the instruction pointer to another location.

```
if($ohNo) {
    // String will look like "Path\To\Classname::someMethod(): That can't be done..."
    $this->result->addError(__METHOD__ . "(): That can't be done because $reasons.");
    return;
}
```

If you really, _really_ want the stack trace, you can do something like this:
```
$result->addError('foo.stack-trace', debug_backtrace(limit: 30));
```

In practice, I care about the stack trace only on rare occasions; and when I do want it, I have to reproduce the bug locally, which means I'm going to have it anyway. That means I can drop a red dot next to a line number, press the green button, and a fraction of a second later the stack trace is right there in the bottom left corner.

The reason I almost never need the stack trace is that I develop with TDD, which makes it excruciating to write huge methods that know and do too many things. (That is one of the best reasons to adopt TDD; such methods tend to cause horrendous problems when you try to change them, or anything they touch.) The `ResultNotification`'s error message carries the same text I would pass if I was `new`ing an `Exception`, _and_ a stack trace if I want one. In practice, I'm going to take that information, write a test case that provokes the bug, and then adjust the production code until the test passes.