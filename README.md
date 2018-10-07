## Bulk Feed Import
This Drupal 8 module makes it easy to import multiple articles extracted from a news feed to your content in the desired language (if your installation is multilingual).
It supports RSS and ATOM feeds.

By default, the articles are imported into Drupal's 'article' content type. But that can easily be changed as for now, like so:
```php
// ImportForm.php - submitForm()

Node::create([
    'type' => 'article',    // Specify the content type to use
    'title' => $items[$id]['title'],
    'body' => [             // The field that will include the article's body
        'value' => $body,
        'format' => 'basic_html'
    ],
    'status' => 1,          // Published
    'langcode' => $form_state->getValue('lang'),
])->save();
```

### TODO

* Support image import.
* Dynamically choose the content type to use with field mapping.