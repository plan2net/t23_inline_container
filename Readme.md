# TYPO3 Extension `t23_inline_container`

An extension to manage content elements inline in `b13/container` similar like possible in `gridelementsteam/gridelements` before.

|            | URL                                                                                                                        |
|------------|----------------------------------------------------------------------------------------------------------------------------|
| Repository | [https://github.com/team23/t23_inline_container/](https://github.com/team23/t23_inline_container/)                         |
| TER        | [https://extensions.typo3.org/extension/t23_inline_container](https://extensions.typo3.org/extension/t23_inline_container) |


## Background
The extension `gridelementsteam/gridelements` provided a field to add content elements inline.
Thereby it was possible to build more complex content pages using grids inside e.g. `georgringer/news`.
Since `b13/container` no longer uses a database field in the container itself, this is no longer possible.

This extension basically adds this field again to every container CType to provide the same behaviour as before with `gridelementsteam/gridelements`.

## Features
Adds a field "Content elements" to all container to make contained elements editable inline.

![](Resources/Public/Images/Screenshot.png)

## Installation & configuration
There is no configuration needed, just install with `composer req team23/t23-inline-container`.
The field will be added automatically to every registered container.

### Hint: Consistent use of maxitems in container column definitions
When defining container columns in your TCA, make sure you handle the maxitems setting consistently across all columns:
either define maxitems for every column, or omit it for all columns.

Inconsistent usage (e.g., setting maxitems on only some columns) can lead to unexpected behavior when TYPO3 or the container extension calculates overall limits.

For Example:
````php
[
    ['name' => 'left side', 'colPos' => 201, 'maxitems' => 2],
    ['name' => 'right side', 'colPos' => 202, 'maxitems' => 2]
]
# or
[
    ['name' => 'left side', 'colPos' => 201],
    ['name' => 'right side', 'colPos' => 202]
]
````

## Compatibility

| `t23_inline_container` | TYPO3 | `b13/container` |
|------------------------|-------|-----------------|
| 13.0.0                 | 13    | 4               |
