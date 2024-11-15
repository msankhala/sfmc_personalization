/**
 * @file
 * Example sitemap configuration for Salesforce Personalize.
 *
 * This example demonstrates how to use drupalSettings to read data exposed by
 * the Drupal site.
 * Note: This requires loading Salesforce Personalize script in the site footer
 * since drupalSettings is loaded in the footer.
 *
 * @author Mahesh Sankhala
 */

// See https://developer.salesforce.com/docs/marketing/personalization/guide/sitemap-implementation.html
function getCookieDomain() {
    const currentDomain = window.location.hostname;
    let validDomains = [];

    if (
        drupalSettings.sfmc_personalize &&
        drupalSettings.sfmc_personalize.global_config &&
        drupalSettings.sfmc_personalize.global_config.allowed_domains
    ) {
        validDomains =  drupalSettings.sfmc_personalize.global_config.allowed_domains;
    }


    if (validDomains.includes(currentDomain)) {
        return currentDomain;
    }
}

function getSfmcContactKey() {
    if (
        drupalSettings.user &&
        drupalSettings.user.field_contact_id
    ) {
        return drupalSettings.user.field_contact_id;
    }
}

function getGlobalContentZones() {
    let globalContentZones = [];
    if (
        drupalSettings.sfmc_personalize &&
        drupalSettings.sfmc_personalize.global_config &&
        drupalSettings.sfmc_personalize.global_config.content_zones
    ) {
        globalContentZones = drupalSettings.sfmc_personalize.global_config.content_zones;
    }

    return globalContentZones;
}

function pathToRegex(path) {
  // Remove leading and trailing slashes
  path = path.replace(/^\/|\/$/g, '');

  // Replace variable segments with regex capturing groups
  path = path.replace(/:(\w+)/g, '(?<$1>[^/]+)');

  // Wrap the URL in start and end anchors
  return `^/${path}/?$`;
}

function getPageTypes() {
    let pageTypes = [];
    if (
        drupalSettings.sfmc_personalize &&
        drupalSettings.sfmc_personalize.pages_config
    ) {
        // Create pageTypes array by iterating the pages_config.
        drupalSettings.sfmc_personalize.pages_config.forEach((pageType) => {
            const Obj = {};
            Obj.name = pageType.name;
            Obj.conditionType = pageType.condition_type;
            Obj.interaction = { name: pageType.name };
            Obj.contentZones = pageType.content_zones;

            // if condition type is path then set isMatch to test against window.location.pathname.
            if (pageType.condition_type == 'path') {
                Obj.isMatch = () => {
                    console.log(pageType.condition_type);
                    const regex = new RegExp(pathToRegex(pageType.path))
                    return regex.test(window.location.pathname);
                }
            }

            // if condition type is regex then set isMatch to test against window.location.pathname.
            if (pageType.condition_type == 'regex') {
                console.log(pageType.condition_type);
                Obj.isMatch = () => {
                    const regex = new RegExp(pageType.regex);
                    return regex.test(window.location.pathname);
                }
            }

            // if condition type is content type then set isMatch to test if given page is one of content type
            if (pageType.condition_type == 'content_type') {
                console.log(pageType.condition_type);
                Obj.isMatch = () => {
                    // Check if body has the content type class
                    const bodyClasses = document.body.classList;
                    let isContentType = false;
                    for (let value in pageType.content_type) {
                        const contentTypeClass = `page-node-type-${value}`;
                        console.log(value);

                        if (bodyClasses.contains(contentTypeClass)) {
                            console.log('this is ', value);
                            isContentType = true;
                        }
                    }
                    return isContentType;
                }
            }

            pageTypes.push(Obj);

        });
    }

    return pageTypes;
}

(function (Drupal, drupalSettings) {
    // Early return if we're not on a valid domain.
    console.log('domain', window.location.hostname);
    if (!getCookieDomain()) {
        console.log(
          'This is not a valid domain for SF interaction studio'
        );
        return;
    }


    SalesforceInteractions.init({
        cookieDomain: getCookieDomain(),
        consents: [{
            purpose: SalesforceInteractions.mcis.ConsentPurpose.Personalization,
            // @todo: See if this needs to be provided dynamically.
            provider: 'CCF Consent Manager',
            status: SalesforceInteractions.ConsentStatus.OptIn,
          }],
    }).then(() => {
        const siteMapConfig = {
          global: {
            locale: 'en_US',
            onActionEvent: (event) => {
                // Retrieve the Marketing Cloud Contact Key from your website.
                const marketingCloudContactKey = getSfmcContactKey();

                // Set the Marketing Cloud Contact Key as the identity attribute.
                if (marketingCloudContactKey) {
                    event.user = event.user || {};
                    event.user.identities = event.user.identities || {};
                    event.user.identities.sfmcContactKey = marketingCloudContactKey;
                }
                event.user = event.user || {};
                event.user.attributes = event.user.attributes || {};
                event.user.attributes.persona = drupalSettings.user.persona;
                event.user.attributes.zipCode = drupalSettings.user.field_zipcode;
                return event;
            },
            contentZones: getGlobalContentZones(),
            listeners: [],
          },
          pageTypeDefault: {
            name: 'default',
            interaction: {
              name: 'Default Page',
            }
          },
          pageTypes: getPageTypes(),
        };
        console.log(siteMapConfig);
        console.log(drupalSettings);
        window.sfmcSiteMapConfig = siteMapConfig
        SalesforceInteractions.initSitemap(siteMapConfig);
    });

})(Drupal, drupalSettings);
