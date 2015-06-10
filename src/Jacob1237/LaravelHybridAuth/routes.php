<?php

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
            SocialAuth::logout();
            Auth::logout();

            return Redirect::to(Config::get('laravelhybridauth::routes.logoutRedirect', '/'));
        }
    ));
}
