<?xml version="1.0" encoding="UTF-8"?>
<!-- credit: https://gist.github.com/gabetax/1702774 (modified for XSLT 1.0) -->
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:functx="http://www.functx.com" version="1.0">

    <xsl:output method="text" />
    <xsl:strip-space elements="*" />

    <!-- Required for li indenting -->
    <!-- Implementation of repeat-string using XSLT 1.0 constructs -->
    <xsl:template name="repeat-string">
        <xsl:param name="stringToRepeat" />
        <xsl:param name="count" />
        <xsl:if test="$count > 0">
            <xsl:value-of select="$stringToRepeat"/>
            <xsl:call-template name="repeat-string">
                <xsl:with-param name="stringToRepeat" select="$stringToRepeat"/>
                <xsl:with-param name="count" select="$count - 1"/>
            </xsl:call-template>
        </xsl:if>
    </xsl:template>

    <xsl:template match="/html/body">
        <xsl:apply-templates select="*" />
    </xsl:template>

    <xsl:template match="li">
        <xsl:if test="normalize-space(.) != ''">
            <xsl:call-template name="repeat-string">
                <xsl:with-param name="stringToRepeat" select="'    '"/>
                <xsl:with-param name="count" select="count(ancestor::li)"/>
            </xsl:call-template>
            <xsl:choose>
                <xsl:when test="name(..) = 'ol'">
                    <xsl:value-of select="position()" />
                    <xsl:text>. </xsl:text>
                </xsl:when>
                <xsl:otherwise>
                    <xsl:text>* </xsl:text>
                </xsl:otherwise>
            </xsl:choose>
            <xsl:value-of select="normalize-space(text())" />
            <xsl:apply-templates select="*[not(self::ul) and not(self::ol)]" />
            <xsl:text>&#xa;&#xa;</xsl:text>
            <xsl:apply-templates select="ul|ol" />
        </xsl:if>
    </xsl:template>

    <!-- Don't process text() nodes for these - prevents unnecessary whitespace -->
    <xsl:template match="ul|ol">
        <xsl:apply-templates select="*[not(self::text())]" />
    </xsl:template>

    <xsl:template match="a">
        <xsl:choose>
            <xsl:when test="not(@href) or @href = ''">
                <xsl:value-of select="normalize-space(.)" />
            </xsl:when>
            <xsl:when test="normalize-space(.) = ''">
                <!-- skip -->
            </xsl:when>
            <xsl:otherwise>
                <xsl:text>[</xsl:text>
                <xsl:value-of select="normalize-space(.)" />
                <xsl:text>](</xsl:text>
                <xsl:value-of select="@href" />
                <xsl:text>)</xsl:text>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>

    <xsl:template match="img">
        <xsl:text>![</xsl:text>
        <xsl:value-of select="@alt" />
        <xsl:text>](</xsl:text>
        <xsl:value-of select="@src" />
        <xsl:text>)</xsl:text>
    </xsl:template>


    <xsl:template match="strong|b">
        <xsl:text>**</xsl:text>
        <xsl:value-of select="normalize-space(.)" />
        <xsl:text>**</xsl:text>
    </xsl:template>
    <xsl:template match="em|i">
        <xsl:text>*</xsl:text>
        <xsl:value-of select="normalize-space(.)" />
        <xsl:text>*</xsl:text>
    </xsl:template>
    <xsl:template match="code">
        <!-- todo: skip the ` if inside a pre -->
        <xsl:text>`</xsl:text>
        <xsl:value-of select="normalize-space(.)" />
        <xsl:text>`</xsl:text>
    </xsl:template>

    <xsl:template match="br">
        <xsl:text>  &#xa;</xsl:text>
    </xsl:template>

    <!-- Block elements -->
    <xsl:template match="hr">
        <xsl:text>----&#xa;&#xa;</xsl:text>
    </xsl:template>

    <xsl:template match="p|div">
        <xsl:apply-templates select="*|text()" />
        <xsl:text>&#xa;&#xa;</xsl:text>        <!-- Block element -->
    </xsl:template>

    <xsl:template match="h1|h2|h3|h4|h5|h6">
        <xsl:call-template name="repeat-string">
            <xsl:with-param name="stringToRepeat" select="'#'"/>
            <xsl:with-param name="count" select="number(substring(name(), 2))"/>
        </xsl:call-template>
        <xsl:text></xsl:text>
        <xsl:value-of select="normalize-space(.)" />
        <xsl:text>&#xa;&#xa;</xsl:text>        <!-- Block element -->
    </xsl:template>

    <xsl:template match="pre">
        <xsl:text></xsl:text>
        <xsl:call-template name="replace-newlines">
            <xsl:with-param name="text" select="text()"/>
            <xsl:with-param name="prefix" select="'    '"/>
        </xsl:call-template>
        <xsl:text>&#xa;&#xa;</xsl:text>        <!-- Block element -->
    </xsl:template>

    <xsl:template match="blockquote">
        <xsl:text>&gt;   </xsl:text>
        <xsl:call-template name="replace-newlines">
            <xsl:with-param name="text" select="text()"/>
            <xsl:with-param name="prefix" select="'&gt; '"/>
        </xsl:call-template>
        <xsl:text>&#xa;&#xa;</xsl:text>        <!-- Block element -->
    </xsl:template>

    <!-- Helper template to replace newlines with prefix + newline -->
    <xsl:template name="replace-newlines">
        <xsl:param name="text"/>
        <xsl:param name="prefix"/>
        <xsl:choose>
            <xsl:when test="contains($text, '&#xa;')">
                <xsl:value-of select="substring-before($text, '&#xa;')"/>
                <xsl:text>&#xa;</xsl:text>
                <xsl:value-of select="$prefix"/>
                <xsl:call-template name="replace-newlines">
                    <xsl:with-param name="text" select="substring-after($text, '&#xa;')"/>
                    <xsl:with-param name="prefix" select="$prefix"/>
                </xsl:call-template>
            </xsl:when>
            <xsl:otherwise>
                <xsl:value-of select="$text"/>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>

    <xsl:template match="text()">
        <xsl:value-of select="normalize-space(.)" />
    </xsl:template>

    <!-- Ignore these elements and their content -->
    <xsl:template match="menu|script|style|meta|link|head|title|noscript" />
</xsl:stylesheet>