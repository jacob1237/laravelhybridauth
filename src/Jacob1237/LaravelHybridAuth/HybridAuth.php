<?php

namespace Jacob1237\LaravelHybridAuth;

use Hybrid_Auth;
use Hybrid_Provider_Adapter;
use Hybrid_User_Profile;
use Illuminate\Support\Facades\Auth;


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
    protected $adapter_profile;

    /**
     * The name of the current provider, e.g. Facebook, LinkedIn, etc
     *
     * @var string
     */
    protected $provider;

    /**
     * Create a new Social instance
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get a social profile for a user, optionally specifying which social network to get, and which user to query
     */
    public function getProfile($network = NULL, $user = NULL)
    {
        //if the supplied $user value is null, use the current user
        if ($user === NULL) {
            $user = Auth::user();

            //if there is no current user, exit out
            if (!$user) {
                return NULL;
            }
        }

        //if the provided network is null, grab the user's first existing social profile
        if ($network === NULL) {
            $profile = $user->profiles()->first();
        } //otherwise get the specific social profile for this network
        else {
            $profile = $user->profiles()->where('network', $network)->first();
        }

        return $profile;
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

        //iterate over the social providers in the config
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
     * Attempt to log in with a given provider
     *
     * @param string $provider
     * @param Hybrid_Auth $hybridauth
     */
    public function attemptAuthentication($provider, Hybrid_Auth $hybridauth)
    {
        $profile = NULL;

        try {
            $this->provider = $provider;
            $adapter = $hybridauth->authenticate($provider);

            $this->setAdapter($adapter);
            $this->setAdapterProfile($adapter->getUserProfile());

            $profile = $this->findProfile();
        }
        catch (\Exception $e) {
            \Log::error("LaravelHybridAuth: " . $e->getMessage());
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
        return $this->adapter_profile;
    }

    /**
     * Sets the current adapter profile
     *
     * @param \Hybrid_User_Profile $profile
     */
    public function setAdapterProfile(Hybrid_User_Profile $profile)
    {
        $this->adapter_profile = $profile;
    }

    /**
     * Finds a user's adapter profile
     *
     * @return mixed
     */
    protected function findProfile()
    {
        $adapter_profile = $this->getAdapterProfile();

        $ProfileModel = $this->config['db']['profilemodel'];
        $UserModel = $this->config['db']['usermodel'];

        $user = NULL;

        // Check if the provider profile already exists
        $profile_builder = call_user_func_array(
            "$ProfileModel::where",
            array('provider', $this->provider)
        );

        // We found an existing user
        if ($profile = $profile_builder->where('identifier', $adapter_profile->identifier)->first()) {
            $user = $profile->user()->first();
        } elseif ($adapter_profile->email) {
            // It's a new profile, but it may not be a new user, so check the users by email
            $user_builder = call_user_func_array(
                "$UserModel::where",
                array('email', $adapter_profile->email)
            );

            $user = $user_builder->first();
        }

        // If we haven't found a user, we need to create a new one
        if (!$user) {
            $user = new $UserModel();

            // Map in anything from the profile that we want in the User
            $map = $this->config['db']['profiletousermap'];
            foreach ($map as $apkey => $ukey) {
                $user->$ukey = $adapter_profile->$apkey;
            }

            // Setup additional user fields (according to db.php config)
            $values = $this->config['db']['uservalues'];
            foreach ($values as $key => $value) {
                if (is_callable($value)) {
                    $value = $value($user, $adapter_profile);
                }

                $user->setAttribute($key, $value);
            }

            if (!$user->save($this->config['db']['userrules'])) {
                throw new \Exception('LaravelHybridAuth: Unable to save User model');
            }
        }

        if (!$profile) {
            $profile = $this->createProfileFromAdapterProfile($adapter_profile, $user);
        } else {
            $profile = $this->applyAdapterProfileToExistingProfile($adapter_profile, $profile);
        }

        if (!$profile->save()) {
            throw new \Exception('LaravelHybridAuth: Unable to save Profile model');
        }

        return $profile;
    }

    /**
     * Creates a social profile from a HybridAuth adapter profile
     *
     * @param \Hybrid_User_Profile $adapter_profile
     * @param \User $user
     * @return \Profile
     */
    protected function createProfileFromAdapterProfile($adapter_profile, $user)
    {
        $ProfileModel = $this->config['db']['profilemodel'];
        $foreignKey = $this->config['db']['profilesforeignkey'];

        $profile = new $ProfileModel();
        $profile->provider = $this->provider;
        $profile->setAttribute($foreignKey, $user->getKey());

        return $this->applyAdapterProfileToExistingProfile($adapter_profile, $profile);
    }

    /**
     * Saves an existing social profile with data from a HybridAuth adapter profile
     *
     * @param \Hybrid_User_Profile $adapter_profile
     * @param \Profile $profile
     * @return \Profile
     */
    protected function applyAdapterProfileToExistingProfile($adapter_profile, $profile)
    {
        $profile->fill(get_object_vars($adapter_profile));
        return $profile;
    }
}
