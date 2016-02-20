<?php

class NP_SitemapExporter extends NucleusPlugin {

   /* ==========================================================================================
    * SitemapExporter for Nucleus
    *
    * Copyright 2005-2007 by Niels Leenheer
    * ==========================================================================================
    * This program is free software and open source software; you can redistribute
    * it and/or modify it under the terms of the GNU General Public License as
    * published by the Free Software Foundation; either version 2 of the License,
    * or (at your option) any later version.
    *
    * This program is distributed in the hope that it will be useful, but WITHOUT
    * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
    * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
    * more details.
    *
    * You should have received a copy of the GNU General Public License along
    * with this program; if not, write to the Free Software Foundation, Inc.,
    * 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA  or visit
    * http://www.gnu.org/licenses/gpl.html
    * ==========================================================================================
    * Version History:
    * v0.5-lm1 2015-01-02: by Leo  (http://nucleus.slightlysome.net/leo)
    * - Tested and updated to run on PHP 5.4
    * - Will now work correctly with LMReplacementVars plugin installed.
    * - Will not show items in the sitemap with date and time in the future. 
    */


    function getName()      {return 'SitemapExporter';}
    function getAuthor()    {return 'Niels Leenheer, Leo, nucleuscms.org';}
    function getURL()       {return 'https://github.com/NucleusCMS/NP_SitemapExporter/';}
    function getVersion()   {return '0.6';}
    function getEventList() {return array('PostAddItem');}
    function supportsFeature($feature) {return in_array($feature,array('SqlTablePrefix'));}
    function getDescription() {
        return sprintf('This plugin provides a sitemap for your website. Google Sitemap URL: %s, Yahoo! Sitemap URL: %s',$this->_sitemapURL('google'),$this->_sitemapURL('yahoo'));
    }
    
    function doAction($type)
    {
        global $CONF, $manager;
        
        if ($type !== 'google' && $type !== 'yahoo') return;
        
        $sitemap = array();
        
        $blog_res = sql_query('SELECT * FROM '.sql_table('blog'));
        
        while ($blog = sql_fetch_array($blog_res))
        {
            if ($this->getBlogOption($blog['bnumber'], 'IncludeSitemap') == 'yes')
            {
                if ($blog['bnumber'] != $CONF['DefaultBlog']) {
                    $sitemap[] = array(
                        'loc'        => $this->_prepareLink($blog['bnumber'], createBlogidLink($blog['bnumber'])),
                        'priority'   => '1.0',
                        'changefreq' => 'daily'
                    );
                }
                else
                {
                    $sitemap[] = array(
                        'loc'        => $blog['burl'],
                        'priority'   => '1.0',
                        'changefreq' => 'daily'
                    );
                }
                
                $cat_res = sql_query(sprintf('SELECT * FROM %s WHERE cblog=%s ORDER BY catid', sql_table('category'), $blog['bnumber']));
                
                while ($cat = sql_fetch_array($cat_res))
                {
                    $sitemap[] = array(
                        'loc'        => $this->_prepareLink($blog['bnumber'], createCategoryLink($cat['catid'])),
                        'priority'   => '1.0',
                        'changefreq' => 'daily'
                    );
                }
                
                $b = & $manager->getBlog($blog['bnumber']);
                
                $item_res = sql_query('
                    SELECT 
                        *,
                        UNIX_TIMESTAMP(itime) AS timestamp
                    FROM 
                        '.sql_table('item').' 
                    WHERE
                        iblog = '.$blog['bnumber'].' AND
                        idraft = 0
                        AND itime <= '.mysqldate($b->getCorrectTime()).'
                    ORDER BY 
                        inumber DESC
                ');
                
                $now = $_SERVER['HTTP_REQUEST_TIME'];
                while ($item = sql_fetch_array($item_res))
                {
                    $tz = date('O', $item['timestamp']);
                    $tz = substr($tz, 0, 3) . ':' . substr($tz, 3, 2);
                    
                    $pasttime = $now - $item['timestamp'];
                    if     ($pasttime < 86400 *  2) $fq = 'hourly';
                    elseif ($pasttime < 86400 * 14) $fq = 'daily'; 
                    elseif ($pasttime < 86400 * 62) $fq = 'weekly';
                    else                            $fq = 'monthly';
                    
                    $sitemap[] = array(
                        'loc' => $this->_prepareLink($blog['bnumber'], createItemLink($item['inumber'])),
                        'lastmod' => gmdate('Y-m-d\TH:i:s', $item['timestamp']) . $tz,
                        'priority' => '1.0',
                        'changefreq' => $fq
                    );
                }
            }
        }
        
        $eventdata = array ('sitemap' => & $sitemap);
        $manager->notify('SiteMap', $eventdata);
        
        if ($type == 'google')
        {
            header ("Content-type: application/xml");
            echo "<?xml version='1.0' encoding='UTF-8'?>\n\n";
            echo "<urlset xmlns='http://www.sitemaps.org/schemas/sitemap/0.9' ";
            echo "xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' ";
            echo "xsi:schemaLocation='http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd'>\n";
            
            $tpl = "\t\t<%s>%s</%s>\n";
            foreach ($sitemap as $url)
            {
                echo "\t<url>\n";
                
                foreach($url as $key=>$value)
                {
                    echo sprintf($tpl, $key, htmlspecialchars($value, ENT_QUOTES, _CHARSET), $key);
                }
                
                echo "\t</url>\n";
            }
            echo "</urlset>\n";
        }
        else
        {
            header ("Content-type: text/plain");
            foreach ($sitemap as $url)
            {
                echo $url['loc'] . "\n";
            }
        }
        exit;
    }
    
    function _prepareLink($blogid, $url) {
        global $manager, $CONF;
        
        if (substr($url, 0, 7) == 'http://') return $url;
        else
        {
            if (substr($url, 0, 11) == '/action.php') $url = substr($url, 11);
            
            $b = & $manager->getBlog($blogid);
            $burl = $b->getURL();
            
            if ($burl == '') $burl = $CONF['IndexURL'];
            
            if ($CONF['URLMode'] == 'pathinfo') {
                $burl = preg_replace('@/[a-z0-9]\.php$@i', '', $burl);
                $burl = preg_replace('@/$@i', '', $burl);
                
                if (substr($url, 0, 1) == '/') return $burl . $url;
                else                           return $burl . '/' . $url;
            }
            else {
                $burl = preg_replace('@/$@i', '', $burl);

                if (preg_match('@/([^/]+\.php)$@i', $burl, $matches)) {
                    $base = $matches[1];
                    $burl = preg_replace('@/[^/]+\.php$@i', '', $burl);
                }
                else $base = 'index.php';
                
                $url = preg_replace('/^index\.php/i', '', $url);
                $url = preg_replace('/^action\.php/i', '', $url);
                
                return $burl . '/' . $base . $url;
            }
        }
    }
    
    function _sitemapURL($type = 'google') {
        global $CONF;
        
        if     ($type == 'google') $url = $this->getOption('GoogleSitemapURL');
        elseif ($type == 'yahoo')  $url = $this->getOption('YahooSitemapURL');
        
        if($url=='')
            $url = sprintf('%s?action=plugin&name=SitemapExporter&type=%s', $CONF['ActionURL'], $type);
        return $url;
    }
    
    function event_PostAddItem(&$data) {
        if ($this->getOption('PingGoogle') !== 'yes') return;
        $url = 'http://www.google.com/webmasters/sitemaps/ping?sitemap=' . urlencode($this->_sitemapURL());
        file_get_contents($url);
    }

    function install() {
        $this->createOption('PingGoogle',         'Ping Google after adding a new item',       'yesno', 'yes');
        $this->createOption('GoogleSitemapURL',   'Alternative Google Sitemap URL',            'text', '');
        $this->createOption('YahooSitemapURL',    'Alternative Yahoo! Sitemap URL',            'text', '');
        $this->createBlogOption('IncludeSitemap', 'Include this blog in the Sitemap Exporter', 'yesno', 'yes');
    }
}
