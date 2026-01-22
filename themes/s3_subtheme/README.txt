SPROWT3 SUBTHEME SASS GUIDE
--------------------------------

This directory includes the scss files that will be compiled into css/style.css

Sass is a language that is just normal CSS plus some extra features, like
variables, nested rules, math, mixins, etc.
To learn more about Sass, visit: http://sass-lang.com


FILE STRUCTURE
--------------------------------

Not every parent theme scss file is represented in the subtheme. New scss files
can be added to this structure and will automatically be included in the compiled css
as long as they are placed within the following folders:

1. variables
    - global values used throughout subtheme.
2. mixins
    - mixins allow you to define styles that can be re-used throughout your stylesheet.
3. base
    - resets and base element styles (typogrophy, list, media, table).
4. layout
    - page and region grid and arrangement styles 
5. components
    a. elements
        - smallest components on the page.
          These are not block specific (buttons, form fields, media, animations).
    b. blocks
        - block type specific styles.
    c. navigation
        - navigation styles.
    d. node
        - content type specific styles (including each view mode styles).
    e. paragraph
        - field group specific styles.
    f. sections
        - Layout Builders section styles (the 'block rows' of the pages).

NOTE: Please do your best to keep any overriding styles in it's most appropriate scss folder/file


GETTING STARTED
--------------------------------

When you begin theming the subtheme, you should start with the following:

1. COLORS: /variables/_colors.scss
    - Set the Primary and Secondary colors and add Tertiary color if needed. If you
      have trouble identifying primary/secondary colors, please consult with designer.
2. Primary / Secondary color value: /components/sections/__base.scss
    - If the primary color is a light color (text on top should be black), or the
      secondary color is dark (text on top should be white), change the .bg-primary or
      .bg-secondary to extend the appropriate selector.
3. TYPOGRAPHY / BORDER RADIUS / BASE BACKGROUND: /variables/__base.scss
    - If base font, background color or block border radius needs changed.
4. BUTTON STYLES: /components/_elements/_buttons.scss
    - using the variables, you can update the default button colors and border-radiuses.
5. HEADER / FOOTER: /layout/_header.scss, /layout/_footer.scss, /variables/_grid-settings.scss
    - Set header heights in grid-settings.scss to make tablet/mobile heights work.
    - Set colors.
6. NAVIGATION: /navigation/_primary_megamenu.scss, /navigation/_utility.scss
    - Set primary and utility navigation colors and spacing
7. BLOCKS & CONTENT TYPES: /components/blocks/, /components/node/
    - Style specific content types view modes / view display / blocks.


COMPILING CSS/JS WITH GULP
--------------------------------

Make sure that you have previous run ‘npm install‘ within your subtheme folder.

To automatically generate the CSS versions of the scss while you are doing theme
development, you can then use ‘gulp watch‘ within the subtheme folder.

You can also just compile sass via ‘gulp sass‘ or js via ‘gulp scripts‘