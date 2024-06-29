# Dynamic content block

## INTRODUCTION
- Displays the dynamic content block for the user.

## INSTALLATION
- Consult [Drupal Module](https://www.drupal.org/docs/extending-drupal/installing-modules) to see how to install and manage modules in Drupal 8.

### Composer
If you use Composer, you can install 'Dynamic Content Block' module using below command:
```bash
composer require sivakarthik229/dynamic_block
```

## CONFIGURATION
- If you go to `/admin/config/dynamic-content-block/settings` you will see a fairly simple interface.
- Select priority for dynamic content.
- Provide the nodes based on the priority selected.
- Click the "Save configuration" button and you are good to go.

## Block Configuration
- If you go to `/admin/structure/block` then click on place the block.
- Search for `Dynamic content` and select the respective region.
- Click the "Save block" button.
- Arrange the block according to the required weight.
- Click the "Save blocks" and you are good to go.
