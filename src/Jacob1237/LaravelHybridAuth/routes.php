<?php

// Login route
if (Config::get('laravelhybridauth::routes.login'))
{
	Route::get(Config::get('laravelhybridauth::routes.login'), array(
		'as' => 'laravelhybridauth.routes.login',
		function ($provider)
		{
			$app = app();
			$profile = $app['laravelhybridauth']->attemptAuthentication($provider, $app['hybridauth']);

			if ($profile) {
				Auth::loginUsingId($profile->user->getKey());
			}

			return Redirect::to(Config::get('laravelhybridauth::routes.loginRedirect', '/'));
		}
	))->where('provider', '(' . implode(
            '|', array_keys(Config::get('laravelhybridauth::hybridauth.providers'))) . ')'
    );
}

// The endpoint that the social provider reroutes to
if (Config::get('laravelhybridauth::routes.endpoint'))
{
	Route::get(Config::get('laravelhybridauth::routes.endpoint'), array(
		'as' => 'laravelhybridauth.routes.endpoint',
        function () {
			Hybrid_Endpoint::process();
		}
	));
}

if (Config::get('laravelhybridauth::routes.logout'))
{
    Route::get(Config::get('laravelhybridauth::routes.logout'), array(
        'as' => 'laravelhybridauth.routes.logout',
        function () {
            App::make('hybridauth')->logoutAllProviders();
            Auth::logout();

            return Redirect::to(Config::get('laravelhybridauth::routes.logoutRedirect', '/'));
        }
    ));
}
