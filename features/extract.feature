Feature: Extract single site from multisite

  Scenario: Extract main site
    Given a WP multisite install
    When I run `wp extract 1`
    Then STDOUT should contain:
      """
      Success: archive-1.tar.gz created!
      """

    When I run `wp db tables | wc -l`
    Then STDOUT should be:
      """
      19
      """

  Scenario: Extract non-existent site
    Given a WP multisite install
    When I try `wp extract 2`
    Then STDERR should be:
      """
      Error: Given blog id is invalid.
      """

  Scenario: Extract sub site
    Given a WP multisite install
    And I run `wp site create --slug=newsite --porcelain`
    And save STDOUT as {SITE_ID}
    And I run `wp option get home`
    And save STDOUT as {HOME_URL}

    When I run `wp extract {SITE_ID}`
    Then STDOUT should contain:
      """
      Success: archive-{SITE_ID}.tar.gz created!
      """

    When I run `wp db tables --url={HOME_URL}/newsite | wc -l`
    Then STDOUT should be:
      """
      19
      """
