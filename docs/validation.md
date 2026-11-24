# Validation

`Kurama\Validator` is a small rule engine with grouped errors and no locale/database dependency.

## Basic usage

```php
$validator = Kurama\Validator::make($_POST, [
    'name' => 'required|string|min:3',
    'email' => 'required|email',
    'age' => 'nullable|integer|min:18',
]);

if ($validator->fails()) {
    $errors = $validator->errors();
}
```

Rules may also be arrays: `'name' => ['required', 'min:3']`.

## Inside an application

```php
$app->post('/signup', function (Request $request) use ($app): Response {
    $data = $app->validate($request->all(), [
        'email' => 'required|email',
        'password' => 'required|min:8|confirmed',
    ]);

    return Response::json($data);
});
```

`Application::validate()` returns only validated fields. On failure it throws `ValidationException`, rendered as **422** with an `errors` key in JSON responses.

## Available rules

| Rule | Meaning |
| --- | --- |
| `required` | Not null, not empty string, not empty array. |
| `nullable` | Stops validation of this field when the value is null. |
| `string`, `integer`, `numeric`, `boolean`, `array` | Type checks. |
| `email` | `filter_var()` email validation. |
| `min:N` | Minimum string length, array count or numeric value. |
| `max:N` | Maximum string length, array count or numeric value. |
| `in:a,b,c` | Value must be one of the listed strings. |
| `regex:/pattern/` | Custom PCRE (a `~` delimiter is added when absent). |
| `confirmed` | Field must equal `field_confirmation`. |
| `same:other` | Field must equal `other`. |

Unknown rules throw `InvalidArgumentException`.

## Custom messages

```php
new Validator($data, $rules, ['email.required' => 'Email is mandatory.']);
```

## API

`make()`, `passes()`, `fails()`, `errors()`, `validated()`.

`ValidationException::errors()` exposes the grouped error array.
