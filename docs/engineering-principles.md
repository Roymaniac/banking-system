# Engineering Principles

* `declare(strict_types=1);` in every PHP file.
* Domain layer never imports Laravel classes.
* Controllers only translate HTTP to application commands.
* One application service per use case.
* One aggregate per repository.
* Immutable value objects.
* Domain events for side effects.
* All money operations are atomic.
* All financial commands are idempotent.
