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
```

### Get existing filter rules
```php
$filters = $rb->getFilterRules();
```

### Get existing filter rules by criteria
```php
$filters = $rb->getFilterRules(['src-mac-address' => 'aa:bb:cc:dd:ee:ff']);
```

### Add new filter rules
```php
$filters = $rb->addFilterRule([
		'chain' => 'forward', 
		'action' => 'drop', 
		'src-mac-address' => 'aa:bb:cc:dd:ee:ff',
	]);
```

### Get existing NAT
```php
$nat = $rb->getNAT();
```

### Get existing NAT by criteria
```php
$nat = $rb->getNAT(['src-mac-address' => 'aa:bb:cc:dd:ee:ff']);
```

### Add new NAT
```php
$nat = $rb->addNAT([
		'chain' => 'forward', 
		'action' => 'redirect', 
		'src-mac-address' => 'aa:bb:cc:dd:ee:ff',
	]);
```

### Get existing Mangle
```php
$mangle = $rb->getMangle();
```

### Get existing Mangle by criteria
```php
$mangle = $rb->getMangle(['src-mac-address' => 'aa:bb:cc:dd:ee:ff']);
```

### Add new Mangle
```php
$mangle = $rb->addMangle([
		'chain' => 'prerouting', 
		'action' => 'mark-routing', 
		'src-mac-address' => 'aa:bb:cc:dd:ee:ff',
	]);
```

### Get existing Address List
```php
$mangle = $rb->getAddressLists();
```

### Get existing Address List by criteria
```php
$mangle = $rb->getAddressLists(['address' => '10.10.1.1']);
```

### Add new Address List
```php
$mangle = $rb->addAddressList([
		'list' => 'LIST', 
		'address' => 10.10.1.1', 
		'timeout' => '01:00:00',
	]);
```
