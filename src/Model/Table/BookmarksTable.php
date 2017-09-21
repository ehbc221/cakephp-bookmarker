<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Bookmarks Model
 *
 * @property \Cake\ORM\Association\BelongsTo $Users
 * @property \Cake\ORM\Association\BelongsToMany $Tags
 *
 * @method \App\Model\Entity\Bookmark get($primaryKey, $options = [])
 * @method \App\Model\Entity\Bookmark newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\Bookmark[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Bookmark|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Bookmark patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Bookmark[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\Bookmark findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class BookmarksTable extends Table
{

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->table('bookmarks');
        $this->displayField('title');
        $this->primaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
            'joinType' => 'INNER'
        ]);
        $this->belongsToMany('Tags', [
            'foreignKey' => 'bookmark_id',
            'targetForeignKey' => 'tag_id',
            'joinTable' => 'bookmarks_tags'
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator)
    {
        $validator
            ->integer('id')
            ->allowEmpty('id', 'create');

        $validator
            ->allowEmpty('title');

        $validator
            ->allowEmpty('description');

        $validator
            ->allowEmpty('url');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules)
    {
        $rules->add($rules->existsIn(['user_id'], 'Users'));

        return $rules;
    }

    // L'argument $query est une instance de \Cake\ORM\Query.
    // Le tableau $options contiendra les tags que nous avons passé à find('tagged')
    // dans l'action de notre Controller
    public function findTagged(Query $query, array $options)
    {
        $bookmarks = $this->find()
            ->select(['id', 'url', 'title', 'description']);

        if (empty($options['tags'])) {
            $bookmarks
                ->leftJoinWith('Tags')
                ->where(['Tags.title IS' => null]);
        } else {
            $bookmarks
                ->innerJoinWith('Tags')
                ->where(['Tags.title IN ' => $options['tags']]);
        }

        return $bookmarks->group(['Bookmarks.id']);
    }

    /**
     * [beforeSave description]
     * @param  [type] $event   [description]
     * @param  [type] $entity  [description]
     * @param  [type] $options [description]
     * @return [type]          [description]
     */
    public function beforeSave($event, $entity, $options)
    {
        if ($entity->tag_string) {
            $entity->tags = $this->_buildTags($entity->tag_string);
        }
    }

    /**
     * [_buildTags description]
     * @param  [type] $tagString [description]
     * @return [type]            [description]
     */
    protected function _buildTags($tagString)
    {
        // Trim tags
        $newTags = array_map('trim', explode(',', $tagString));
        // Retire tous les tags vides
        $newTags = array_filter($newTags);
        // Réduit les tags dupliqués
        $newTags = array_unique($newTags);

        $out = [];
        $query = $this->Tags->find()
            ->where(['Tags.title IN' => $newTags]);

        // Retire les tags existants de la liste des tags nouveaux.
        foreach ($query->extract('title') as $existing) {
            $index = array_search($existing, $newTags);
            if ($index !== false) {
                unset($newTags[$index]);
            }
        }
        // Ajoute les tags existants.
        foreach ($query as $tag) {
            $out[] = $tag;
        }
        // Ajoute les nouveaux tags.
        foreach ($newTags as $tag) {
            $out[] = $this->Tags->newEntity(['title' => $tag]);
        }
        return $out;
    }
}
