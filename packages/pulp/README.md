# Pulp

Stream-based file processing library for PHP, inspired by Gulp.

## Features

- **Stream-based processing:** Handle files through a pipeline of handlers.
- **Fluent API:** Easily chain operations with a simple and intuitive syntax.
- **Extensible:** Create custom handlers or use format-specific packages.
- **File globbing:** Support for multiple glob patterns to select source files.

## API

### Pulp::start()
a shortcut for `new Pulp()`

### Pulp::src($patterns)
- string|array< string > *$patterns* one or more glob patterns or file paths

Adds a new File object for every matching file

### Pulp::dest($directory)
- string *$directory* target directory

Writes all File objects into the *$directory* target directory

### Pulp::map($callback)
- callable(File) *$callback* will be called for every File object.
	The File object is passed as the first argument to the callback.
	The callback should **return** a File object or null. The File
	object will be passed to the next handler. In case of a null the File
	will be dropped.

### Pulp::merge($pulps...)
- Pulp *$pulps*
Merges two or more Pulp streams together.

### Pulp::shadow($callback)
- callable(Pulp $pulp) *$callback*

Creates a "shadow" Pulp stream and pass it to the callback.
Every File in the stream can be modified without affecting the main stream.

### Pulp::results($callback)
- callable(array< File >) *$callback*

Waits until the stream ends and pass an array with all Files to $callback.

### Pulp::fileSwitch($patterns, $defaultCb)
- hash< string, callable(Pulp $pulp) > *$patterns*
- callable *$defaultCb*


```
->pipe(Pulp::fileSwitch(
	array(
		'*.php' => function($p) {
			//...
		},
		'*.js' => function($p) {
			//...
		}
	),
	function($p) {
		// default
	}
))
```

## Examples
```
use \OpenMapsight\Pulp;

Pulp::start()
	->pipe(Pulp::src('mydir/*.txt'))
	->pipe(Pulp::map(function($f) {
		$f->content .= 'fo';
		return $f;
	}))
	->pipe(Pulp::dest('myotherdir'))
	->run();
```
