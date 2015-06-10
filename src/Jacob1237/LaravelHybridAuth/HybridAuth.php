<?php

namespace Jacob1237\LaravelHybridAuth;

use Hybrid_Auth;
use Hybrid_Provider_Adapter;
use Hybrid_User_Profile;
use Illuminate\Support\FacadesLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;


class HybridAuth
{
    /**
     * The package's configuration
     *
     * @var array
     */
    protected $config;

    /**
     * The service used to login
     *
     * @var Hybrid_Provider_Adapter $adapter
     */
    protected $adapter;

    /**
     * The profile of the current user from the provider, once logged in
     *
     * @var Hybrid_User_Profile
     */
    protected $adapterProfile;

    /**
     * The name of the current provider, e.g. Facebook, LinkedIn, etc
     *
     * @var string
     */
    protected $provider;

    /**
     * User profile
     *
     * @var \Profile
     */
    protected $profile = null;

    /**
     * Create a new Social instance
     *
     * @param array $config
     */
    public function __construct(array $config, Hybrid_Auth $hybridAuth)
    {
        $this->config = $config;
        $this->hybridAuth = $hybridAuth;

        $this->provider = Session::get('SocialAuth::provider', null);
        $this->adapterProfile = unserialize(Session::get('SocialAuth::profile', null));
    }

    /**
     * Gets the enabled social providers from the config
     *
     * @return array
     */
    public function getProviders()
    {
        $haconfig = $this->config['hybridauth'];
        $providers = array();

        // Iterate over the social providers in the config
        foreach (array_get($haconfig, 'providers', array()) as $provider => $config) {
            if (array_get($config, 'enabled')) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }

    /**
     * Returns the current social provider
     *
     * @return string
     */
    public function getCurrentProvider()
    {
        return $this->provider;
    }

    /**
     * Sets the current social provider
     *
     * @param string $provider
     */
    public function setCurrentProvider($provider)
    {
        $this->provider = $provider;
    }

    /**
     * Gets the current provider adapter
     *
     * @return \Hybrid_Provider_Adapter
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * Sets the current provider adapter
     *
     * @param \Hybrid_Provider_Adapter $adapter
     */
    public function setAdapter(Hybrid_Provider_Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * Attempt authenticate
     *
     * If user profile is already exists, authorize him.
     *
     * If there is no profile, but user is already logged in,
     * create new profile and append it to the current user.
     *
     * If there is no profile and no user, return false.
     *
     * @param $provider
     * @throws \Exception
     * @return bool
     */
    public function attempt($provider)
    {
        $adapter = null;
        $this->provider = $provider;

        try {
            $adapter = $this->hybridAuth->authenticate($provider);

            if (empty($adapter)) {
                return false;
            }
        } catch(\Exception $e) {
            Log::error('LaravelHybridAuth: ' . $e->getMessage());
            throw new \Exception('Errors during social login', 1);
        }

        $this->setAdapter($adapter);
        $this->setAdapterProfile($adapter->getUserProfile());

        $profile = $this->profile();

        if ($profile) {
            $this->updateProfile($profile);
            Auth::loginUsingId($profile->user->getKey());
        } elseif(Auth::check()) {
            $this->createProfile(Auth::user());
        } else {
            return false;
        }

        return true;
    }

    /**
     * Check if user is logged in
     * through the social network
     *
     * @return bool
     */
    public function check()
    {
        return $this->adapterProfile ? true : false;
    }

    /**
     * Logout from social accounts
     */
    public function logout()
    {
        $this->hybridAuth->logoutAllProviders();

        Session::remove('SocialAuth::profile');
        Session::remove('SocialAuth::provider');
    }

    /**
     * Get profile
     *
     * @return bool
     */
    public function profile()
    {
        if (!empty($this->profile)) {
            return $this->profile;
        }

        $profileModel = $this->config['db']['profilemodel'];
        $profile = $profileModel::where('provider', $this->provider)
            ->where('identifier', $this->adapterProfile->identifier)
            ->first();

        if ($profile) {
            $this->profile = $profile;
        }

        return $profile;
    }

    /**
     * Gets the current adapter profile
     *
     * @return \Hybrid_User_Profile
     */
    public function getAdapterProfile()
    {
        return $this->adapterProfile;
    }

    /**
     * Sets the current adapter profile
     *
     * @param \Hybrid_User_Profile $profile
     */
    public function setAdapterProfile(Hybrid_User_Profile $profile)
    {
        $this->adapterProfile = $profile;

        Session::set('SocialAuth::provider', $this->provider);
        Session::set('SocialAuth::profile', serialize($profile));
    }

    /**
     * Create new profile
     *
     * @param \Eloquent $user
     * @return \Eloquent
     */
    public function createProfile($user)
    {
        $model = $this->config['db']['profilemodel'];;

        /** @var \Eloquent $profile */
        $profile = new $model();
        $profile->provider = $this->provider;
        $profile->fill(get_object_vars($this->adapterProfile));

        $user->profiles()->save($profile);

        return $profile;
    }

    /**
     * Update existent profile
     *
     * @param \Eloquent $profile
     * @return \Eloquent
     */
    public function updateProfile($profile)
    {
        $profile->fill(get_object_vars($this->adapterProfile));
        $profile->save();

        return $profile;
    }
}
