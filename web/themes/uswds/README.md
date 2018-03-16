Drupal integration of the U.S. Web Design Standards library.

This base theme focuses on tweaking Drupal's markup so that it will work with the USWDS library. Some CSS is added to deal with unavoidable Drupal quirks, but only as a last resort.

Subtheming

As with most Drupal themes, it's recommended that your active theme be a subtheme of this one, to make updates easier. Simply copy out the /examples/my_subtheme folder to get started, following the directions in /examples/my_subtheme/README.md.

Customizing

The theme makes no assumptions about how you would like to add site-specific CSS. You can either:

1. Use the pre-compiled USWDS library

If you would like to use the pre-compiled USWDS library, download the zip file, extract and rename the folder to "assets", and place it in your theme folder. Afterwards, this should be a valid path inside your subtheme folder: `assets/css/uswds.css`

2. Use npm to include the USWDS Sass source files

The library's source can be installed directly in the subtheme directory by running `npm install` in that location. After doing this, you can set up your assets by copying them from node_modules/uswds/dist, and then running a basic npm script to compile the CSS. The commands to do all of this are:

npm install
cp -r node_modules/uswds/dist assets
npm run build

Or if you have a preferred front-end workflow you can adjust the package.json file accordingly.

Menus

In USWDS there are four styles of menus: Primary menu, Secondary menu (upper right, by "Search"), Footer menu, and Sidenav. You can implement these menus simply by placing a menu block into the appropriate region. (Eg, you would put the menu block for your primary menu in the "Primary menu" region.)

Note: For the three "menu regions" (Primary menu, Secondary menu, Footer menu) it is expected that you will only put a single block inside them. (Putting additional blocks inside these regions will have no effect.) By contrast, the "First Sidebar" region can have any number of blocks in it - and all menu blocks will display as "sidenav" menus.

Configuration

After installation, see the theme settings inside Drupal for various customizations, mostly involving the header and the footer.

Notes

Note: This code was originally forked from this repository, and was split off at 18F's suggestion.