<?php namespace Laraplus\Data;

trait Translatable
{
    protected $locale = null;

    protected $fallbackLocale = null;

    protected $onlyTranslated = null;

    protected $withFallback = null;

    /**
     * Translated attributes cache
     *
     * @var array
     */
    protected static $i18nAttributes = [];

    /**
     * Boot the trait.
     */
    public static function bootTranslatable()
    {
        static::addGlobalScope(new TranslatableScope);
    }

    /**
     * Save a new model and return the instance.
     *
     * @param array $attributes
     * @param array|string $translations
     * @return static
     */
    public static function create(array $attributes = [], $translations = [])
    {
        $model = new static($attributes);

        $model->save();

        if(is_array($translations)) {
            $model->saveTranslations($translations);
        }

        return $model;
    }

    /**
     * Save a new model in provided locale and return the instance.
     *
     * @param string $locale
     * @param array $attributes
     * @param array|string $translations
     * @return static
     */
    public static function createInLocale($locale, array $attributes = [], $translations = [])
    {
        $model = new static($attributes);

        $model->setLocale($locale)->save();

        if(is_array($translations)) {
            $model->saveTranslations($translations);
        }

        return $model;
    }

    /**
     * Save a new model and return the instance. Allow mass-assignment.
     *
     * @param array $attributes
     * @param array|string $translations
     * @return static
     */
    public static function forceCreate(array $attributes, $translations = [])
    {
        $model = new static;

        return static::unguarded(function () use ($model, $attributes, $translations) {
            return $model->create($attributes, $translations);
        });
    }

    /**
     * Save a new model in provided locale and return the instance. Allow mass-assignment.
     *
     * @param array $attributes
     * @param array|string $translations
     * @return static
     */
    public static function forceCreateInLocale($locale, array $attributes, $translations = [])
    {
        $model = new static;

        return static::unguarded(function () use ($locale, $model, $attributes, $translations) {
            return $model->createInLocale($locale, $attributes, $translations);
        });
    }

    /**
     * @param array $translations
     * @return bool
     */
    public function saveTranslations(array $translations)
    {
        $backup = $this->getLocale();
        $success = true;

        foreach($translations as $locale => $attributes) {
            $this->setLocale($locale);
            $this->fill($attributes);

            $success &= $this->save();
        }

        $this->setLocale($backup);

        return $success;
    }

    /**
     * @param array $translations
     * @return bool
     */
    public function forceSaveTranslations(array $translations)
    {
        return static::unguarded(function () use ($translations) {
            return $this->saveTranslations($translations);
        });
    }

    /**
     * @param $locale
     * @param array $attributes
     * @return bool
     */
    public function saveTranslation($locale, array $attributes)
    {
        return $this->saveTranslations([
            $locale => $attributes
        ]);
    }

    /**
     * @param $locale
     * @param array $attributes
     * @return bool
     */
    public function forceSaveTranslation($locale, array $attributes)
    {
        return static::unguarded(function () use ($locale, $attributes) {
            return $this->saveTranslation($locale, $attributes);
        });
    }

    /**
     * @param array $attributes
     * @return $this
     * @throws MassAssignmentException
     */
    public function fill(array $attributes)
    {
        if(!isset(static::$i18nAttributes[$this->getTable()])) {
            $this->initTranslatableAttributes();
        }

        return parent::fill($attributes);
    }

    /**
     * Init translatable attributes.
     */
    protected function initTranslatableAttributes()
    {
        if (property_exists($this, 'translatable')) {
            $attributes = $this->translatable;
        } else {
            $attributes = $this->getTranslatableAttributesFromSchema();
        }

        static::$i18nAttributes[$this->getTable()] = $attributes;
    }

    /**
     * Get an array of translatable attributes from schema.
     *
     * @return array
     */
    protected function getTranslatableAttributesFromSchema()
    {
        if ((!$con = $this->getConnection()) || (!$builder = $con->getSchemaBuilder())) {
            return [];
        }

        if($columns = TranslatableConfig::cacheGet($this->getTable())) {
            return $columns;
        }

        $columns = $builder->getColumnListing($this->getTable());

        TranslatableConfig::cacheSet($this->getTable(), $columns);

        return $columns;
    }

    /**
     * Get an array of translatable attributes.
     *
     * @return array
     */
    public function translatableAttributes()
    {
        return static::$i18nAttributes[$this->getTable()];
    }

    /**
     * Get name of the locale key.
     *
     * @return string
     */
    public function getLocaleKey()
    {
        return TranslatableConfig::dbKey();
    }

    /**
     * Get current locale
     *
     * @param $locale
     * @return string
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Get current locale
     *
     * @return string
     */
    public function getLocale()
    {
        if($this->locale) {
            return $this->locale;
        }

        return TranslatableConfig::currentLocale();
    }

    /**
     * Get current locale
     *
     * @param $locale
     * @return string
     */
    public function setFallbackLocale($locale)
    {
        $this->fallbackLocale = $locale;

        return $this;
    }

    /**
     * Get current locale
     *
     * @return string
     */
    public function getFallbackLocale()
    {
        if(!is_null($this->fallbackLocale)) {
            return $this->fallbackLocale;
        }

        return TranslatableConfig::fallbackLocale();
    }

    /**
     * Set if model should select only translated rows
     *
     * @param bool $onlyTranslated
     * @return $this
     */
    public function setOnlyTranslated($onlyTranslated)
    {
        $this->onlyTranslated = $onlyTranslated;

        return $this;
    }

    /**
     * Get current locale
     *
     * @return string
     */
    public function getOnlyTranslated()
    {
        if(!is_null($this->onlyTranslated)) {
            return $this->onlyTranslated;
        }

        return TranslatableConfig::onlyTranslated();
    }

    /**
     * Get the i18n table associated with the model.
     *
     * @return string
     */
    public function getI18nTable()
    {
        return $this->getTable() . $this->getTranslationTableSuffix();
    }

    /**
     * Get the i18n table suffix.
     *
     * @return string
     */
    public function getTranslationTableSuffix()
    {
        return TranslatableConfig::dbSuffix();
    }

    /**
     * Should fallback to a primary translation.
     *
     * @return bool
     */
    public function shouldFallback()
    {
        $onlyTranslated = $this->getOnlyTranslated();
        $fallback = $this->getFallbackLocale();
        $locale = $this->getLocale();

        return $fallback && !$onlyTranslated && $locale != $fallback;
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }

    /**
     * Get a new query builder instance for the connection.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function newBaseQueryBuilder()
    {
        $conn = $this->getConnection();

        $grammar = $conn->getQueryGrammar();

        $builder = new QueryBuilder($conn, $grammar, $conn->getPostProcessor());

        return $builder->setModel($this);
    }
}