<?php

namespace Jacob1237\LaravelHybridAuth\Facades;


class HybridAuth extends \Illuminate\Support\Facades\Facade
{
	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor()
    {
        return 'laravelhybridauth';
    }
}
