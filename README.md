# RouterOS
RouterOS PHP Class, with namespace and some wrappers

## Usage


### Router Board setting
enable api service on the router board
```
>ip service enable api
```
or using ssl
```
>ip service enable api-ssl
```

### Connect to router board
```php
$ip = '10.1.1.1';
$user = 'user';   //rb user
$pass = 'pass';   //rb user's password

$rb = new \am05mhz\RouterOS();
$rb->connect($ip, $user, $pass);

### Get existing filter rules
```php
$filters = $rb->getFilterRules();
```

### Get existing filter rules by criteria
```php
$filters = $rb->getFilterRules(['src-mac-address' => 'aa:bb:cc:dd:ee:ff']);
```

### Get existing NAT
```php
$filters = $rb->getNAT();
```

### Get existing NAT by criteria
```php
$filters = $rb->getNAT(['src-mac-address' => 'aa:bb:cc:dd:ee:ff']);
```

### Get existing Mangle
```php
$filters = $rb->getMangle();
```

### Get existing Mangle by criteria
```php
$filters = $rb->getMangle(['src-mac-address' => 'aa:bb:cc:dd:ee:ff']);
```
